<?php

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Mail\AnalyticsReportMail;
use App\Domain\Analytics\Models\AnalyticsReport;
use App\Domain\Analytics\Support\TrafficReport;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Traits\Localizable;

/**
 * Sends one scheduled report to each of its recipients separately, so no
 * one sees the others' addresses (ADR-0040).
 */
final class SendAnalyticsReport
{
    use Localizable;

    public function handle(AnalyticsReport $report, ?CarbonImmutable $on = null): int
    {
        $on ??= CarbonImmutable::now(EthiopianCalendar::timezone());
        [$from, $to] = $report->period($on);
        $traffic = new TrafficReport($from, $to, $report->locale_filter);
        $locale = $report->mail_locale;
        $calendar = app(EthiopianCalendar::class);
        $period = $calendar->format($from, $locale).' – '.$calendar->format($to, $locale);
        $rows = $this->withLocale($locale, fn (): array => $traffic->rows($report->dimension, 500));

        $sent = 0;
        foreach (array_slice($report->recipients, 0, AnalyticsReport::MAX_RECIPIENTS) as $address) {
            if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            Mail::to($address)->locale($locale)->queue(new AnalyticsReportMail($report->name, $report->dimension, $period, $traffic->total(), $rows));
            $sent++;
        }

        $report->forceFill(['last_sent_at' => CarbonImmutable::now()])->save();

        return $sent;
    }
}
