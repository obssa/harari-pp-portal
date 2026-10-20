<?php

namespace App\Domain\Events\Actions;

use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\EventRegistration;
use Carbon\CarbonImmutable;

/**
 * Gives a place back, by the person from their ticket or by staff. A freed
 * place goes to the first person on the waiting list (ADR-0025).
 */
final class CancelRegistration
{
    public function __construct(
        private readonly SendRegistrationMessage $messages,
        private readonly FillFromWaitlist $waitlist,
    ) {}

    public function handle(EventRegistration $registration): void
    {
        if ($registration->status === RegistrationStatus::Cancelled) {
            return;
        }

        $freesPlace = $registration->status === RegistrationStatus::Registered;
        $registration->status = RegistrationStatus::Cancelled;
        $registration->cancelled_at = CarbonImmutable::now();
        $registration->save();

        $this->messages->handle($registration, SendRegistrationMessage::CANCELLED);
        if ($freesPlace) {
            $this->waitlist->handle($registration->event);
        }
    }
}
