<?php

namespace App\Console\Commands;

use App\Domain\Events\Actions\SendRegistrationMessage;
use App\Domain\Events\Enums\RegistrationStatus;
use App\Domain\Events\Models\EventRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reminds everyone with a place, once, when their event is less than a day
 * away (PLAN.md §4: reminders by SMS and email; runs every hour).
 */
final class SendEventRemindersCommand extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Remind registrants of events starting within the next 24 hours';

    public function handle(SendRegistrationMessage $messages): int
    {
        $now = CarbonImmutable::now('UTC');
        $count = 0;

        EventRegistration::query()
            ->where('status', RegistrationStatus::Registered->value)
            ->whereNull('reminded_at')
            ->whereHas('event', fn (Builder $event) => $event->whereBetween('starts_at', [$now, $now->addDay()]))
            ->with('event.translations')
            ->chunkById(200, function ($registrations) use ($messages, $now, &$count): void {
                /** @var EventRegistration $registration */
                foreach ($registrations as $registration) {
                    $registration->reminded_at = $now;
                    $registration->save();
                    $messages->handle($registration, SendRegistrationMessage::REMINDER);
                    $count++;
                }
            });

        $this->components->info("Reminders sent: {$count}");

        return self::SUCCESS;
    }
}
