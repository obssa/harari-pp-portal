<?php

namespace App\Domain\Analytics\Support;

/**
 * A traffic report as CSV for Excel (ADR-0040): UTF-8 with a byte-order
 * mark so Ethiopic page addresses survive.
 */
final class ReportCsv
{
    /**
     * @param  list<array{key: string, label: string, locale: ?string, views: int, share: float}>  $rows
     */
    public static function make(string $dimension, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, [TrafficReport::dimensionLabel($dimension), __('analytics.column.language'), __('analytics.column.views'), __('analytics.column.share')], escape: '');
        foreach ($rows as $row) {
            fputcsv($handle, [$row['label'], $row['locale'] ?? '', $row['views'], $row['share']], escape: '');
        }
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
