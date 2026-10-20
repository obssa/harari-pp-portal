<?php

namespace App\Console\Commands;

use App\Domain\Analytics\Actions\SendAnalyticsReport;
use App\Domain\Analytics\Models\AnalyticsReport;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Every morning: weekly reports go out on Mondays, monthly ones on the 1st
 * (ADR-0040).
 */
final class SendAnalyticsReportsCommand extends Command
{
    protected $signature = 'analytics:send-reports';

    protected $description = 'Email the traffic reports that are due today';

    public function handle(SendAnalyticsReport $send): int
    {
        $today = CarbonImmutable::now(EthiopianCalendar::timezone());
        $count = 0;
        foreach (AnalyticsReport::query()->get() as $report) {
            if ($report->isDue($today)) {
                $send->handle($report, $today);
                $count++;
            }
        }
        $this->line("{$count} report(s) sent.");

        return self::SUCCESS;
    }
}
