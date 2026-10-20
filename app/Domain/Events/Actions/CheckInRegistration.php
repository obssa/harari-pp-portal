<?php

namespace App\Domain\Events\Actions;

use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\EventRegistration;
use App\Models\User;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Marks a ticket as used at the door (ADR-0025). Only confirmed places are
 * let in, and a ticket is used once.
 */
final class CheckInRegistration
{
    public function handle(EventRegistration $registration, User $actor): void
    {
        if ($registration->status !== RegistrationStatus::Registered) {
            throw ValidationException::withMessages(['code' => __('events.check_in.not_registered', ['status' => $registration->status->label()])]);
        }
        if ($registration->checked_in_at !== null) {
            throw ValidationException::withMessages(['code' => __('events.check_in.already', [
                'time' => $registration->checked_in_at->setTimezone(EthiopianCalendar::timezone())->format('H:i'),
            ])]);
        }

        $registration->checked_in_at = CarbonImmutable::now();
        $registration->checked_in_by = $actor->id;
        $registration->save();
    }
}
