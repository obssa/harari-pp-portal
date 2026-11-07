<?php

namespace App\Domain\Engagement\Actions;

use App\Domain\Engagement\Mail\ConfirmSubscriptionMail;
use App\Domain\Engagement\Models\Subscriber;
use App\Domain\Engagement\Support\SubscriptionOptions;
use App\Domain\Translation\Support\Locales;
use App\Services\Sms\SendSms;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
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

    /** Cache key prefix of a new number waiting for its code (ADR-0077). */
    private const NEW_PHONE = 'newsletter-new-phone:';

    /**
     * @param  array<string, mixed>  $input
     * @return array{subscriber: Subscriber, code_sent: bool, email_to: ?string, phone_to: ?string} where the link and the code went
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

        // ADR-0077: the link and the code always go to what was typed. A subscriber found by their other
        // detail gets the new address or number only once they confirm it, so nobody is moved by a typo.
        if ($email !== '') {
            $this->sendLink($subscriber, $subscriber->email !== $email ? $email : null);
        }
        $codeSent = $phone !== null && $this->sendCode($subscriber, $subscriber->phone !== $phone ? $phone : null);

        return ['subscriber' => $subscriber, 'code_sent' => $codeSent, 'email_to' => $email !== '' ? $email : null, 'phone_to' => $codeSent ? $phone : null];
    }

    /**
     * The link in the email: confirms the address (and asks for news again
     * after an unsubscribe). A link sent to a new address carries it,
     * encrypted, and switches the subscriber to it unless another
     * subscription already uses it (ADR-0077).
     */
    public function confirmEmail(Subscriber $subscriber, ?string $change = null): void
    {
        $new = $change !== null ? $this->changedEmail($change) : null;
        if ($new !== null && ! Subscriber::query()->whereNull('erased_at')->where('email_hash', Subscriber::hash($new))->whereKeyNot($subscriber->id)->exists()) {
            $subscriber->forceFill(['email' => $new, 'email_hash' => Subscriber::hash($new), 'email_confirmed_at' => CarbonImmutable::now()]);
        }
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

        // ADR-0077: a code sent to a new number switches the subscriber to it, unless another subscription uses it.
        $new = Cache::pull(self::NEW_PHONE.$subscriber->id);
        if (is_string($new) && ! Subscriber::query()->whereNull('erased_at')->where('phone_hash', Subscriber::hash($new))->whereKeyNot($subscriber->id)->exists()) {
            $subscriber->forceFill(['phone' => $new, 'phone_hash' => Subscriber::hash($new), 'phone_confirmed_at' => CarbonImmutable::now()]);
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

    /**
     * The confirmation link, to the subscriber's address or, when they typed
     * another one, to that address with the change it confirms.
     */
    private function sendLink(Subscriber $subscriber, ?string $newEmail = null): void
    {
        $change = $newEmail !== null
            ? Crypt::encryptString((string) json_encode(['email' => $newEmail, 'until' => CarbonImmutable::now()->addDays(self::UNCONFIRMED_DAYS)->getTimestamp()]))
            : null;
        try {
            Mail::to($newEmail ?? (string) $subscriber->email)->locale($subscriber->locale)
                ->queue(new ConfirmSubscriptionMail($subscriber->token, $subscriber->locale, $newEmail === null && $subscriber->email_confirmed_at !== null, $change));
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function changedEmail(string $change): ?string
    {
        try {
            $data = json_decode(Crypt::decryptString($change), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        return is_array($data) && is_string($data['email'] ?? null) && (int) ($data['until'] ?? 0) > CarbonImmutable::now()->getTimestamp()
            ? $data['email']
            : null;
    }

    /**
     * A code by SMS to the subscriber's number or, when they typed another
     * one, to that number, which is kept aside until the code confirms it.
     */
    private function sendCode(Subscriber $subscriber, ?string $newPhone = null): bool
    {
        if ($subscriber->code_sent_at !== null && $subscriber->code_sent_at->diffInSeconds(CarbonImmutable::now()) < 60
            && Cache::get(self::NEW_PHONE.$subscriber->id) === $newPhone) {
            return true;
        }
        $code = (string) random_int(100000, 999999);
        $subscriber->forceFill([
            'code_hash' => Subscriber::hash('newsletter:'.$subscriber->id.':'.$code),
            'code_expires_at' => CarbonImmutable::now()->addMinutes(self::CODE_MINUTES),
            'code_attempts' => 0,
            'code_sent_at' => CarbonImmutable::now(),
        ])->save();
        if ($newPhone !== null) {
            Cache::put(self::NEW_PHONE.$subscriber->id, $newPhone, CarbonImmutable::now()->addMinutes(self::CODE_MINUTES));
        } else {
            Cache::forget(self::NEW_PHONE.$subscriber->id);
        }
        SendSms::dispatchSync($newPhone ?? (string) $subscriber->phone, __('newsletter.sms.code', ['code' => $code, 'minutes' => self::CODE_MINUTES], $subscriber->locale));

        return true;
    }
}
