<?php

namespace App\Domain\Analytics\Support;

use App\Domain\Analytics\Models\TrafficDay;
use App\Domain\Translation\Support\Locales;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Reads the daily counts back (ADR-0040): totals, a day-by-day series and the
 * views by page, section, language, referring site, device or country, with
 * each row's share. Dates are days in Addis Ababa time.
 */
final class TrafficReport
{
    public const DIMENSIONS = ['pages', 'sections', 'languages', 'referrers', 'devices', 'countries'];

    public function __construct(
        public readonly DateTimeInterface $from,
        public readonly DateTimeInterface $to,
        public readonly ?string $locale = null,
    ) {}

    public function total(): int
    {
        return (int) $this->pages()->sum('views');
    }

    /**
     * @return array<string, int> date => views, every day of the period
     */
    public function daily(): array
    {
        $counts = $this->pages()->selectRaw('date, SUM(views) as views')->groupBy('date')->pluck('views', 'date')->all();
        $days = [];
        for ($day = CarbonImmutable::parse($this->fromDate()); $day->lessThanOrEqualTo(CarbonImmutable::parse($this->toDate())); $day = $day->addDay()) {
            $days[$day->toDateString()] = (int) ($counts[$day->toDateString()] ?? 0);
        }

        return $days;
    }

    /**
     * @return list<array{key: string, label: string, locale: ?string, views: int, share: float}>
     */
    public function rows(string $dimension, int $limit = 50): array
    {
        $query = match ($dimension) {
            'pages' => $this->pages()->selectRaw('path as value, locale, SUM(views) as views')->groupBy('path', 'locale'),
            'sections' => $this->pages()->selectRaw('section as value, NULL as locale, SUM(views) as views')->groupBy('section'),
            'languages' => $this->pages()->selectRaw('locale as value, locale, SUM(views) as views')->groupBy('locale'),
            'referrers' => $this->traffic(TrafficDay::REFERRER),
            'devices' => $this->traffic(TrafficDay::DEVICE),
            'countries' => $this->traffic(TrafficDay::COUNTRY),
            default => throw new InvalidArgumentException("Unknown dimension {$dimension}."),
        };

        $total = max(1, $this->total());

        return $query->orderByDesc('views')->orderBy('value')->limit($limit)->get()
            ->map(fn (object $row): array => [
                'key' => $dimension.':'.$row->value.':'.($row->locale ?? ''),
                'label' => $this->label($dimension, (string) $row->value),
                'locale' => $row->locale !== null ? (string) $row->locale : null,
                'views' => (int) $row->views,
                'share' => round((int) $row->views / $total * 100, 1),
            ])
            ->values()
            ->all();
    }

    public static function dimensionLabel(string $dimension): string
    {
        return match ($dimension) {
            'pages' => __('analytics.dimension.pages'),
            'sections' => __('analytics.dimension.sections'),
            'languages' => __('analytics.dimension.languages'),
            'referrers' => __('analytics.dimension.referrers'),
            'devices' => __('analytics.dimension.devices'),
            'countries' => __('analytics.dimension.countries'),
            default => $dimension,
        };
    }

    private function label(string $dimension, string $value): string
    {
        return match (true) {
            $dimension === 'languages' && Locales::isSupported($value) => Locales::nativeName($value),
            $dimension === 'sections' && $value === 'home' => __('analytics.value.home'),
            $value === 'direct' => __('analytics.value.direct'),
            $value === 'internal' => __('analytics.value.internal'),
            $value === 'unknown' => __('analytics.value.unknown'),
            $value === 'mobile' => __('analytics.value.mobile'),
            $value === 'tablet' => __('analytics.value.tablet'),
            $value === 'desktop' => __('analytics.value.desktop'),
            default => $value,
        };
    }

    private function fromDate(): string
    {
        return CarbonImmutable::instance($this->from)->setTimezone(EthiopianCalendar::timezone())->toDateString();
    }

    private function toDate(): string
    {
        return CarbonImmutable::instance($this->to)->setTimezone(EthiopianCalendar::timezone())->toDateString();
    }

    private function pages(): Builder
    {
        return DB::table('page_view_days')
            ->whereBetween('date', [$this->fromDate(), $this->toDate()])
            ->when($this->locale, fn (Builder $q, string $locale) => $q->where('locale', $locale));
    }

    private function traffic(string $dimension): Builder
    {
        return DB::table('traffic_days')
            ->selectRaw('value, NULL as locale, SUM(views) as views')
            ->where('dimension', $dimension)
            ->whereBetween('date', [$this->fromDate(), $this->toDate()])
            ->when($this->locale, fn (Builder $q, string $locale) => $q->where('locale', $locale))
            ->groupBy('value');
    }
}
