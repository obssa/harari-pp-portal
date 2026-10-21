<?php

namespace App\Domain\Engagement\Actions;

use App\Domain\Engagement\Mail\ConfirmSubscriptionMail;
use App\Domain\Engagement\Models\Subscriber;
use App\Domain\Engagement\Support\SubscriptionOptions;
use App\Domain\Translation\Support\Locales;
use App\Services\Sms\SendSms;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Newsletter and SMS subscriptions with double opt-in (PLAN.md §2, §4;
 * ADR-0051). An email address is confirmed by a private link, a phone by a
 * six-digit code. Someone already on the list is never told so: they get
 * the same link or code, which leads to their preferences. The choices of an
 * existing subscriber change only in the preference centre.
 */
final class Subscribe
{
    public const CODE_MINUTES = 10;

    public const CODE_ATTEMPTS = 5;

    public const PER_DAY = 5;

    /** Days an unconfirmed sign-up waits before it is deleted. */
    public const UNCONFIRMED_DAYS = 7;

    /**
     * @param  array<string, mixed>  $input
     * @return array{subscriber: Subscriber, code_sent: bool}
     */
    public function handle(array $input, string $locale): array
    {
        $errors = [];
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        if ($email !== '' && (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $errors['email'] = __('events.registration.validation.email');
        }
        $phone = null;
        if (filled($input['phone'] ?? null) && ($phone = PhoneNumber::normalise((string) $input['phone'])) === null) {
            $errors['phone'] = __('events.registration.validation.phone');
        }
        $channels = array_values(array_intersect(SubscriptionOptions::CHANNELS, (array) ($input['channels'] ?? [])));
        if (in_array('email', $channels, true) && $email === '' && ! isset($errors['email'])) {
            $errors['email'] = __('newsletter.validation.email_needed');
        }
        if (in_array('sms', $channels, true) && $phone === null && ! isset($errors['phone'])) {
            $errors['phone'] = __('newsletter.validation.phone_needed');
        }
        if ($channels === []) {
            $errors['channels'] = __('newsletter.validation.channels');
        }
        $topics = array_values(array_intersect(SubscriptionOptions::TOPICS, (array) ($input['topics'] ?? [])));
        if ($topics === []) {
            $errors['topics'] = __('newsletter.validation.topics');
        }
        if (! filter_var($input['consent'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $errors['consent'] = __('events.registration.validation.consent');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $email = in_array('email', $channels, true) ? $email : '';
        $phone = in_array('sms', $channels, true) ? $phone : null;
        $limit = 'newsletter:'.Subscriber::hash($email.'|'.$phone);
        if (RateLimiter::tooManyAttempts($limit, self::PER_DAY)) {
            throw ValidationException::withMessages(['email' => __('newsletter.validation.too_many')]);
        }
        RateLimiter::hit($limit, 86400);

        $subscriber = $this->existing($email, $phone);
        if ($subscriber === null) {
            $subscriber = new Subscriber([
                'locale' => Locales::isSupported($locale) ? $locale : 'en', 'channels' => $channels, 'topics' => $topics,
                'source' => 'form', 'consented_at' => CarbonImmutable::now(),
            ]);
            $subscriber->issueToken();
        }
        // Contact details come back after an unsubscribe removed them (the hashes stayed).
        if ($email !== '' && $subscriber->email === null) {
            $subscriber->forceFill(['email' => $email, 'email_hash' => Subscriber::hash($email)]);
        }
        if ($phone !== null && $subscriber->phone === null) {
            $subscriber->forceFill(['phone' => $phone, 'phone_hash' => Subscriber::hash($phone)]);
        }
        $subscriber->save();

        if ($email !== '') {
            $this->sendLink($subscriber);
        }
        $codeSent = $phone !== null && $this->sendCode($subscriber);

        return ['subscriber' => $subscriber, 'code_sent' => $codeSent];
    }

    /** The link in the email: confirms the address (and asks for news again after an unsubscribe). */
    public function confirmEmail(Subscriber $subscriber): void
    {
        if ($subscriber->email === null) {
            return;
        }
        $subscriber->forceFill([
            'email_confirmed_at' => $subscriber->email_confirmed_at ?? CarbonImmutable::now(),
            'channels' => array_values(array_unique([...$subscriber->channels, 'email'])),
            'unsubscribed_at' => null,
        ])->save();
    }

    public function verifyCode(Subscriber $subscriber, string $typed): void
    {
        $code = (string) preg_replace('/\D/', '', $typed);
        $valid = $subscriber->code_hash !== null && $subscriber->code_expires_at?->isFuture() === true && $subscriber->code_attempts < self::CODE_ATTEMPTS;
        if (! $valid || ! hash_equals((string) $subscriber->code_hash, Subscriber::hash('newsletter:'.$subscriber->id.':'.$code))) {
            if ($valid) {
                $subscriber->forceFill(['code_attempts' => $subscriber->code_attempts + 1])->save();
            }

            throw ValidationException::withMessages(['code' => __('membership.validation.code')]);
        }

        $subscriber->forceFill([
            'code_hash' => null, 'code_expires_at' => null, 'code_attempts' => 0,
            'phone_confirmed_at' => $subscriber->phone_confirmed_at ?? CarbonImmutable::now(),
            'channels' => array_values(array_unique([...$subscriber->channels, 'sms'])),
            'unsubscribed_at' => null,
        ])->save();
    }

    /**
     * The preference centre: language, topics and channels; no channel at all means unsubscribing.
     *
     * @param  array<string, mixed>  $input
     */
    public function updatePreferences(Subscriber $subscriber, array $input): void
    {
        $channels = array_values(array_intersect(SubscriptionOptions::CHANNELS, (array) ($input['channels'] ?? [])));
        $channels = array_values(array_filter($channels, fn (string $channel): bool => $channel === 'email' ? $subscriber->email !== null : $subscriber->phone !== null));
        $topics = array_values(array_intersect(SubscriptionOptions::TOPICS, (array) ($input['topics'] ?? [])));
        $locale = (string) ($input['locale'] ?? $subscriber->locale);
        if ($channels !== [] && $topics === []) {
            throw ValidationException::withMessages(['topics' => __('newsletter.validation.topics')]);
        }
        if ($channels === []) {
            $this->unsubscribe($subscriber);

            return;
        }

        $subscriber->forceFill([
            'channels' => $channels,
            'topics' => $topics,
            'locale' => Locales::isSupported($locale) ? $locale : $subscriber->locale,
            'unsubscribed_at' => null,
        ])->save();
    }

    public function unsubscribe(Subscriber $subscriber): void
    {
        if ($subscriber->unsubscribed_at === null) {
            $subscriber->forceFill(['unsubscribed_at' => CarbonImmutable::now()])->save();
        }
    }

    /** Unconfirmed sign-ups go after a week (ADR-0051). */
    public static function purge(): int
    {
        return Subscriber::query()->withoutGlobalScopes()
            ->whereNull('email_confirmed_at')->whereNull('phone_confirmed_at')
            ->where('created_at', '<', CarbonImmutable::now()->subDays(self::UNCONFIRMED_DAYS))
            ->delete();
    }

    private function existing(string $email, ?string $phone): ?Subscriber
    {
        $hashes = array_filter(['email_hash' => $email !== '' ? Subscriber::hash($email) : null, 'phone_hash' => $phone !== null ? Subscriber::hash($phone) : null]);
        if ($hashes === []) {
            return null;
        }

        return Subscriber::query()->whereNull('erased_at')
            ->where(function ($query) use ($hashes): void {
                foreach ($hashes as $column => $hash) {
                    $query->orWhere($column, $hash);
                }
            })
            ->first();
    }

    private function sendLink(Subscriber $subscriber): void
    {
        try {
            Mail::to((string) $subscriber->email)->locale($subscriber->locale)->queue(new ConfirmSubscriptionMail($subscriber->token, $subscriber->locale, $subscriber->email_confirmed_at !== null));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function sendCode(Subscriber $subscriber): bool
    {
        if ($subscriber->code_sent_at !== null && $subscriber->code_sent_at->diffInSeconds(CarbonImmutable::now()) < 60) {
            return true;
        }
        $code = (string) random_int(100000, 999999);
        $subscriber->forceFill([
            'code_hash' => Subscriber::hash('newsletter:'.$subscriber->id.':'.$code),
            'code_expires_at' => CarbonImmutable::now()->addMinutes(self::CODE_MINUTES),
            'code_attempts' => 0,
            'code_sent_at' => CarbonImmutable::now(),
        ])->save();
        SendSms::dispatchSync((string) $subscriber->phone, __('newsletter.sms.code', ['code' => $code, 'minutes' => self::CODE_MINUTES], $subscriber->locale));

        return true;
    }
}
