<?php

namespace App\Domain\Events\Actions;

use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Notifications\RegistrationNotice;
use App\Domain\Notifications\Support\Recipients;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Takes a place at an event, or a place on its waiting list when it is full
 * (ADR-0025). The same phone number or email address registers once; trying
 * again sends the existing ticket again, to the details already given.
 */
final class RegisterForEvent
{
    public function __construct(private readonly SendRegistrationMessage $messages) {}

    /**
     * @param  array<string, mixed>  $data  name, phone, email, consent
     */
    public function handle(Event $event, array $data, string $locale): RegistrationResult
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($data['name'] ?? '')));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
        $phoneInput = trim((string) ($data['phone'] ?? ''));
        $phone = PhoneNumber::normalise($phoneInput);

        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['name'] = __('events.registration.validation.name');
        }
        if ($phoneInput !== '' && $phone === null) {
            $errors['phone'] = __('events.registration.validation.phone');
        }
        if ($email !== '' && (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $errors['email'] = __('events.registration.validation.email');
        }
        if ($phoneInput === '' && $email === '') {
            $errors['phone'] = __('events.registration.validation.contact');
        }
        if (! filter_var($data['consent'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $errors['consent'] = __('events.registration.validation.consent');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $email = $email === '' ? null : $email;
        $result = DB::transaction(function () use ($event, $name, $email, $phone, $locale): RegistrationResult {
            // One registration at a time per event, so the last place is never given twice.
            $locked = Event::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();

            $existing = $this->existing($locked, $phone, $email);
            if ($existing !== null) {
                return new RegistrationResult($existing, existing: true);
            }

            $status = match ($locked->registrationState()) {
                Event::REGISTRATION_OPEN => RegistrationStatus::Registered,
                Event::REGISTRATION_WAITLIST => RegistrationStatus::Waitlisted,
                default => throw ValidationException::withMessages(['form' => __('events.registration.closed')]),
            };

            $token = Str::random(40);
            /** @var EventRegistration $registration */
            $registration = $locked->registrations()->create([
                'locale' => $locale,
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'phone_hash' => $phone !== null ? EventRegistration::hash($phone) : null,
                'email_hash' => $email !== null ? EventRegistration::hash($email) : null,
                'status' => $status,
                'token' => $token,
                'token_hash' => EventRegistration::hash($token),
            ]);

            return new RegistrationResult($registration, existing: false);
        });

        $registration = $result->registration;
        $this->messages->handle($registration, match (true) {
            $result->existing => SendRegistrationMessage::AGAIN,
            $registration->status === RegistrationStatus::Waitlisted => SendRegistrationMessage::WAITLISTED,
            default => SendRegistrationMessage::CONFIRMED,
        });

        if (! $result->existing) {
            Recipients::notify(Recipients::withPermission('events.registrations'), RegistrationNotice::for($event));
        }

        return $result;
    }

    private function existing(Event $event, ?string $phone, ?string $email): ?EventRegistration
    {
        if ($phone === null && $email === null) {
            return null;
        }

        return $event->registrations()->active()
            ->where(function (Builder $query) use ($phone, $email): void {
                if ($phone !== null) {
                    $query->orWhere('phone_hash', EventRegistration::hash($phone));
                }
                if ($email !== null) {
                    $query->orWhere('email_hash', EventRegistration::hash($email));
                }
            })
            ->first();
    }
}
