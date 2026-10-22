<?php

namespace App\Domain\Events\Support;

use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventTranslation;
use Carbon\CarbonImmutable;

/**
 * iCalendar files (RFC 5545) for one event or the upcoming events, so
 * readers can add them to any calendar (PLAN.md §4: iCal export).
 */
final class IcsCalendar
{
    /**
     * @param  iterable<array{event: Event, translation: EventTranslation, url: string}>  $items
     */
    public static function render(iterable $items, string $name): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Harari Region Prosperity Party//Portal//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::escape($name),
        ];

        $stamp = CarbonImmutable::now('UTC')->format('Ymd\THis\Z');
        foreach ($items as $item) {
            $event = $item['event'];
            $translation = $item['translation'];
            $location = trim(implode(', ', array_filter([$translation->venue, $translation->address ? str_replace("\n", ', ', $translation->address) : null, $event->online_url])));

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$event->id.'-'.$translation->locale.'@'.(string) parse_url((string) config('app.url'), PHP_URL_HOST);
            $lines[] = 'DTSTAMP:'.$stamp;
            if ($event->all_day) {
                $lines[] = 'DTSTART;VALUE=DATE:'.$event->startDay()->format('Ymd');
                $lines[] = 'DTEND;VALUE=DATE:'.$event->dayAfter()->format('Ymd');
            } else {
                $lines[] = 'DTSTART:'.$event->starts_at->utc()->format('Ymd\THis\Z');
                $lines[] = 'DTEND:'.($event->ends_at ?? $event->starts_at->addHours(2))->utc()->format('Ymd\THis\Z');
            }
            $lines[] = 'SUMMARY:'.self::escape($translation->title);
            if (filled($translation->summary)) {
                $lines[] = 'DESCRIPTION:'.self::escape((string) $translation->summary);
            }
            if ($location !== '') {
                $lines[] = 'LOCATION:'.self::escape($location);
            }
            if ($event->hasMap()) {
                $lines[] = 'GEO:'.$event->latitude.';'.$event->longitude;
            }
            $lines[] = 'URL:'.$item['url'];
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(self::fold(...), $lines))."\r\n";
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
    }

    /** Lines longer than 75 bytes continue on the next line after a space, never inside a character. */
    private static function fold(string $line): string
    {
        $out = '';
        $current = '';
        foreach (mb_str_split($line) as $char) {
            if (strlen($current.$char) > 75) {
                $out .= $current."\r\n";
                $current = ' ';
            }
            $current .= $char;
        }

        return $out.$current;
    }
}
