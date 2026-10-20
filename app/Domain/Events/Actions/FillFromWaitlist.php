<?php

namespace App\Domain\Events\Actions;

use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Gives free places to the waiting list, first come first served, and tells
 * each person moved up (ADR-0025). Runs when a place is given back and when
 * an editor raises the number of places.
 */
final class FillFromWaitlist
{
    public function __construct(private readonly SendRegistrationMessage $messages) {}

    /**
     * @return list<EventRegistration> the people moved up
     */
    public function handle(Event $event): array
    {
        $promoted = DB::transaction(function () use ($event): array {
            $locked = Event::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->registration_enabled || $locked->isPast()) {
                return [];
            }

            $free = $locked->capacity === null ? PHP_INT_MAX : $locked->capacity - $locked->registeredCount();
            if ($free <= 0) {
                return [];
            }

            $moved = [];
            /** @var EventRegistration $next */
            foreach ($locked->registrations()->where('status', RegistrationStatus::Waitlisted->value)
                ->orderBy('created_at')->orderBy('id')->limit(min($free, 500))->get() as $next) {
                $next->status = RegistrationStatus::Registered;
                $next->promoted_at = CarbonImmutable::now();
                $next->save();
                $moved[] = $next;
            }

            return $moved;
        });

        foreach ($promoted as $registration) {
            $this->messages->handle($registration, SendRegistrationMessage::PROMOTED);
        }

        return $promoted;
    }
}
