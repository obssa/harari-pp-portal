<?php

namespace App\Console\Commands;

use App\Domain\Events\Models\EventRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes registrations once their event is long over (ADR-0025, PLAN.md
 * §11 personal data protection): contact details are kept only as long as
 * `portal.registration_retention_days` after the event. Runs daily.
 */
final class PurgeRegistrationsCommand extends Command
{
    protected $signature = 'events:purge-registrations';

    protected $description = 'Delete event registrations past the retention period';

    public function handle(): int
    {
        $before = CarbonImmutable::now('UTC')->subDays(max(1, (int) config('portal.registration_retention_days')));

        $deleted = EventRegistration::query()
            ->whereHas('event', fn (Builder $event) => $event->withTrashed()->where(fn (Builder $ended) => $ended
                ->where('ends_at', '<', $before)
                ->orWhere(fn (Builder $open) => $open->whereNull('ends_at')->where('starts_at', '<', $before))))
            ->delete();

        $this->components->info("Registrations deleted: {$deleted}");

        return self::SUCCESS;
    }
}
