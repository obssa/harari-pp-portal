<?php

namespace App\Domain\Engagement\Support;

use App\Domain\Engagement\Models\Campaign;
use App\Domain\Engagement\Models\CampaignDelivery;

/**
 * What happened to a campaign (PLAN.md §7 campaign results, ADR-0053): counts
 * per outcome, people who clicked or unsubscribed, by language. Numbers
 * only; addresses are never part of a report.
 */
final class CampaignReport
{
    public function __construct(private readonly Campaign $campaign) {}

    /**
     * @return array{recipients: int, sent: int, failed: int, skipped: int, queued: int, opened: int, clicked: int, clicks: int, unsubscribed: int}
     */
    public function totals(): array
    {
        $row = $this->campaign->deliveries()->toBase()->selectRaw(
            'count(*) as recipients,'
            ." sum(case when status = 'sent' then 1 else 0 end) as sent,"
            ." sum(case when status = 'failed' then 1 else 0 end) as failed,"
            ." sum(case when status = 'skipped' then 1 else 0 end) as skipped,"
            ." sum(case when status = 'queued' then 1 else 0 end) as queued,"
            .' sum(case when opened_at is not null then 1 else 0 end) as opened,'
            .' sum(case when clicked_at is not null then 1 else 0 end) as clicked,'
            .' coalesce(sum(clicks), 0) as clicks,'
            .' sum(case when unsubscribed_at is not null then 1 else 0 end) as unsubscribed'
        )->first();

        $totals = [];
        foreach (['recipients', 'sent', 'failed', 'skipped', 'queued', 'opened', 'clicked', 'clicks', 'unsubscribed'] as $key) {
            $totals[$key] = (int) ($row->{$key} ?? 0);
        }

        /** @var array{recipients: int, sent: int, failed: int, skipped: int, queued: int, opened: int, clicked: int, clicks: int, unsubscribed: int} $totals */
        return $totals;
    }

    /** Share of the messages sent, as a percentage with one decimal. */
    public static function rate(int $part, int $sent): float
    {
        return $sent > 0 ? round($part * 100 / $sent, 1) : 0.0;
    }

    /**
     * @return list<array{language: string, recipients: int, sent: int, clicked: int, unsubscribed: int}>
     */
    public function byLanguage(): array
    {
        return $this->campaign->deliveries()->toBase()
            ->selectRaw("locale as language, count(*) as recipients, sum(case when status = 'sent' then 1 else 0 end) as sent,"
                .' sum(case when clicked_at is not null then 1 else 0 end) as clicked, sum(case when unsubscribed_at is not null then 1 else 0 end) as unsubscribed')
            ->groupBy('locale')->orderByDesc('recipients')->get()
            ->map(fn (object $row): array => [
                'language' => (string) $row->language, 'recipients' => (int) $row->recipients, 'sent' => (int) $row->sent,
                'clicked' => (int) $row->clicked, 'unsubscribed' => (int) $row->unsubscribed,
            ])->values()->all();
    }

    /**
     * One line per recipient without any address, for the CSV.
     *
     * @param  resource  $out
     */
    public function write($out): void
    {
        fwrite($out, "\u{FEFF}");
        fputcsv($out, array_map(fn (string $key): string => __("campaigns.report.column.{$key}"),
            ['recipient', 'language', 'status', 'sent_at', 'opened_at', 'clicks', 'unsubscribed_at', 'error']), escape: '');
        $this->campaign->deliveries()->orderBy('id')->chunk(1000, function ($deliveries) use ($out): void {
            /** @var CampaignDelivery $delivery */
            foreach ($deliveries as $delivery) {
                fputcsv($out, [
                    __("campaigns.report.type.{$delivery->recipient_type}"), $delivery->locale, __("campaigns.report.status.{$delivery->status}"),
                    $delivery->sent_at?->toIso8601String() ?? '', $delivery->opened_at?->toIso8601String() ?? '', $delivery->clicks,
                    $delivery->unsubscribed_at?->toIso8601String() ?? '', (string) $delivery->error,
                ], escape: '');
            }
        });
    }
}
