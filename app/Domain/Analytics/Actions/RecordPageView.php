<?php

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\Models\TrafficDay;
use App\Domain\Translation\Support\Locales;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Counts one view of a public page (ADR-0040). The page sends its own
 * address, the referring site and its width; Cloudflare adds the country.
 * Only daily totals are kept: no address, cookie, user agent or identifier
 * of the visitor. Bots and browsers asking not to be tracked are skipped.
 */
final class RecordPageView
{
    private const BOTS = '/bot|crawl|spider|slurp|preview|lighthouse|headless|monitor|curl|wget|python|scrapy|facebookexternalhit|embedly|whatsapp|telegram/i';

    /**
     * @param  array<string, mixed>  $data  {p: path, r: referrer host, w: width}
     */
    public function handle(array $data, ?string $userAgent, ?string $country, ?string $doNotTrack): bool
    {
        if ($userAgent === null || $userAgent === '' || preg_match(self::BOTS, $userAgent) === 1 || $doNotTrack === '1') {
            return false;
        }

        $path = $this->path($data['p'] ?? null);
        if ($path === null) {
            return false;
        }
        $locale = explode('/', trim($path, '/'))[0];
        $section = explode('/', trim($path, '/'))[1] ?? 'home';
        $date = CarbonImmutable::now(EthiopianCalendar::timezone())->toDateString();

        DB::table('page_view_days')->upsert(
            [['date' => $date, 'locale' => $locale, 'path' => $path, 'section' => Str::limit($section, 40, ''), 'views' => 1]],
            ['date', 'locale', 'path'],
            ['views' => DB::raw('views + 1')],
        );

        $traffic = [
            [TrafficDay::REFERRER, $this->referrer($data['r'] ?? null)],
            [TrafficDay::DEVICE, $this->device($data['w'] ?? null)],
            [TrafficDay::COUNTRY, $this->country($country)],
        ];
        DB::table('traffic_days')->upsert(
            array_map(fn (array $row): array => ['date' => $date, 'locale' => $locale, 'dimension' => $row[0], 'value' => $row[1], 'views' => 1], $traffic),
            ['date', 'locale', 'dimension', 'value'],
            ['views' => DB::raw('views + 1')],
        );

        return true;
    }

    /** A public page of the site: /{language} or /{language}/…, without query. */
    private function path(mixed $raw): ?string
    {
        if (! is_string($raw) || $raw === '' || strlen($raw) > 600) {
            return null;
        }
        $path = '/'.trim(rawurldecode(Str::before(Str::before($raw, '?'), '#')), '/');
        $first = explode('/', trim($path, '/'))[0];
        if (! in_array($first, Locales::all(), true) || preg_match('/[\x00-\x1F\x7F<>"]/u', $path) === 1 || ! mb_check_encoding($path, 'UTF-8')) {
            return null;
        }

        return mb_substr($path, 0, 300);
    }

    private function referrer(mixed $raw): string
    {
        $host = is_string($raw) ? Str::lower(trim($raw)) : '';
        $own = Str::lower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($host === '' || $host === $own) {
            return $host === '' ? 'direct' : 'internal';
        }
        if (preg_match('/^[a-z0-9.-]{1,120}$/', $host) !== 1) {
            return 'other';
        }

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    private function device(mixed $width): string
    {
        $width = is_numeric($width) ? (int) $width : 0;

        return match (true) {
            $width <= 0 => 'unknown',
            $width < 768 => 'mobile',
            $width < 1024 => 'tablet',
            default => 'desktop',
        };
    }

    private function country(?string $code): string
    {
        $code = strtoupper((string) $code);

        return preg_match('/^[A-Z]{2}$/', $code) === 1 && ! in_array($code, ['XX', 'T1'], true) ? $code : 'unknown';
    }
}
