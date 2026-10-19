<?php

use App\Services\Calendar\EthiopianCalendar;
use App\Settings\SiteSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;

function calendar(): EthiopianCalendar
{
    return app(EthiopianCalendar::class);
}

function utc(string $value): CarbonImmutable
{
    return CarbonImmutable::parse($value, 'UTC');
}

it('converts known dates to the Ethiopian calendar', function (string $gregorian, array $ethiopian) {
    expect(calendar()->toEthiopian(utc($gregorian))->toArray())
        ->toBe(['year' => $ethiopian[0], 'month' => $ethiopian[1], 'day' => $ethiopian[2]]);
})->with([
    'New Year 2019 E.C.' => ['2026-09-11 09:00', [2019, 1, 1]],
    'Pagume 6 in a leap year' => ['2027-09-11 09:00', [2019, 13, 6]],
    'New Year after the leap year' => ['2027-09-12 09:00', [2020, 1, 1]],
    'New Year 2016 E.C. (before a Gregorian leap year)' => ['2023-09-12 09:00', [2016, 1, 1]],
    'Genna (Ethiopian Christmas)' => ['2026-01-07 09:00', [2018, 4, 29]],
    'Today in the build log' => ['2026-10-19 09:00', [2019, 2, 9]],
]);

it('uses the day in Addis Ababa, not in UTC', function () {
    // 22:30 UTC on 10 September is already 01:30 on 11 September in Addis Ababa.
    expect(calendar()->toEthiopian(utc('2026-09-10 22:30'))->toArray())->toBe(['year' => 2019, 'month' => 1, 'day' => 1])
        ->and(calendar()->toEthiopian(utc('2026-09-10 20:59'))->toArray())->toBe(['year' => 2018, 'month' => 13, 'day' => 5]);
});

it('converts every day of two years there and back without drift', function () {
    $day = utc('2026-09-01 12:00');
    for ($i = 0; $i < 730; $i++, $day = $day->addDay()) {
        $ethiopian = calendar()->toEthiopian($day);
        expect(calendar()->toGregorian($ethiopian->year, $ethiopian->month, $ethiopian->day)->toDateString())
            ->toBe($day->setTimezone('Africa/Addis_Ababa')->toDateString());
    }
});

it('rejects dates that do not exist', function (int $year, int $month, int $day) {
    calendar()->toGregorian($year, $month, $day);
})->with([
    'Pagume 6 in a common year' => [2018, 13, 6],
    'month 14' => [2019, 14, 1],
    'day 31' => [2019, 1, 31],
])->throws(InvalidArgumentException::class);

it('formats dates in each language', function (string $locale, string $ethiopian, string $gregorian) {
    $date = utc('2026-10-19 09:00');

    expect(calendar()->formatEthiopian($date, $locale))->toBe($ethiopian)
        ->and(calendar()->formatGregorian($date, $locale))->toBe($gregorian);
})->with([
    'en' => ['en', '9 Tikimt 2019 E.C.', '19 October 2026'],
    'am' => ['am', 'ጥቅምት 9 ቀን 2019 ዓ.ም.', 'ኦክቶበር 19, 2026'],
    'om' => ['om', 'Onkoloolessa 9, 2019 A.L.I.', 'Onkoloolessa 19, 2026'],
    'har falls back to am' => ['har', 'ጥቅምት 9 ቀን 2019 ዓ.ም.', 'ኦክቶበር 19, 2026'],
]);

it('names all thirteen Ethiopian months in every shipped language', function (string $locale) {
    foreach (range(1, 13) as $month) {
        expect(__("calendar.ethiopian.month.{$month}", [], $locale))->not->toStartWith('calendar.');
    }
})->with(['en', 'am', 'om']);

it('shows the Ethiopian date first with the Gregorian date in brackets by default', function () {
    expect(calendar()->format(utc('2026-10-19 09:00'), 'en'))->toBe('9 Tikimt 2019 E.C. (19 October 2026)')
        ->and(calendar()->format(utc('2026-10-19 09:00'), 'en', withTime: true))->toBe('9 Tikimt 2019 E.C. (19 October 2026), 12:00');
});

it('follows the calendar order chosen in the admin', function () {
    $settings = app(SiteSettings::class);
    $settings->calendar_primary = EthiopianCalendar::GREGORIAN;
    $settings->save();

    expect(calendar()->format(utc('2026-10-19 09:00'), 'en'))->toBe('19 October 2026 (9 Tikimt 2019 E.C.)');

    $settings->show_secondary_calendar = false;
    $settings->save();

    expect(calendar()->format(utc('2026-10-19 09:00'), 'en'))->toBe('19 October 2026');
});

it('can show just one calendar on request', function () {
    expect(calendar()->format(utc('2026-10-19 09:00'), 'en', only: EthiopianCalendar::ETHIOPIAN))->toBe('9 Tikimt 2019 E.C.');
});

it('gives the year in both calendars for footers', function () {
    expect(calendar()->years(utc('2026-10-19 09:00')))->toBe('2019 (2026)');
});

it('renders a <time> element with the ISO date', function () {
    app()->setLocale('en');
    $html = Blade::render('<x-date :date="$d" class="text-sm" />', ['d' => utc('2026-10-19 09:00')]);

    expect($html)->toContain('<time datetime="2026-10-19T09:00:00+00:00" class="text-sm">9 Tikimt 2019 E.C. (19 October 2026)</time>')
        ->and(Blade::render('<x-date :date="null" />'))->toBe('');
});

it('offers an @ethiopianDate directive', function () {
    app()->setLocale('am');

    expect(Blade::render('@ethiopianDate($d)', ['d' => utc('2026-09-11 09:00')]))->toBe('መስከረም 1 ቀን 2019 ዓ.ም.');
});

it('shows today in both calendars on public pages', function () {
    $this->travelTo(utc('2026-10-19 09:00'));

    $this->get('/en')->assertSee('9 Tikimt 2019 E.C. (19 October 2026)');
});
