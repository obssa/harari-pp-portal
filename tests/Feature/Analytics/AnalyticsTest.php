<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Analytics\Actions\RecordPageView;
use App\Domain\Analytics\Mail\AnalyticsReportMail;
use App\Domain\Analytics\Models\AnalyticsReport;
use App\Domain\Analytics\Models\PageViewDay;
use App\Domain\Analytics\Support\ReportCsv;
use App\Domain\Analytics\Support\TrafficReport;
use App\Filament\Pages\TrafficReports;
use App\Filament\Resources\AnalyticsReports\AnalyticsReportResource;
use App\Filament\Widgets\TopPages;
use App\Filament\Widgets\TrafficOverview;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;

use function Pest\Livewire\livewire;

const VISITOR_AGENT = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0 Mobile Safari/537.36';

/** Sends the beacon as a browser would. */
function beacon(array $data, string $agent = VISITOR_AGENT, array $headers = []): TestResponse
{
    return test()->call('POST', '/pv', [], [], [], ['HTTP_USER_AGENT' => $agent, 'CONTENT_TYPE' => 'text/plain', ...$headers], (string) json_encode($data));
}

function countVisit(string $path, string $referrer = '', int $width = 1280, ?string $country = 'ET'): void
{
    app(RecordPageView::class)->handle(['p' => $path, 'r' => $referrer, 'w' => $width], VISITOR_AGENT, $country, null);
}

function addisToday(): string
{
    return CarbonImmutable::now(EthiopianCalendar::timezone())->toDateString();
}

it('counts a page view without a cookie or anything about the visitor', function () {
    $response = beacon(['p' => '/en/news', 'r' => 'www.google.com', 'w' => 390], headers: ['HTTP_CF_IPCOUNTRY' => 'ET']);
    beacon(['p' => '/en/news?utm_source=x', 'r' => 'hararipp.example', 'w' => 390]);

    $response->assertNoContent();
    expect($response->headers->getCookies())->toBe([])
        ->and(DB::table('page_view_days')->where(['date' => addisToday(), 'locale' => 'en', 'path' => '/en/news'])->value('views'))->toBe(2)
        ->and(DB::table('page_view_days')->value('section'))->toBe('news')
        ->and(DB::table('traffic_days')->where(['dimension' => 'referrer', 'value' => 'google.com'])->value('views'))->toBe(1)
        ->and(DB::table('traffic_days')->where(['dimension' => 'device', 'value' => 'mobile'])->value('views'))->toBe(2)
        ->and(DB::table('traffic_days')->where(['dimension' => 'country', 'value' => 'ET'])->value('views'))->toBe(1)
        ->and([...Schema::getColumnListing('page_view_days'), ...Schema::getColumnListing('traffic_days')])
        ->not->toContain('ip', 'user_agent', 'visitor', 'session_id');
});

it('ignores bots, browsers that ask not to be tracked, and anything that is not a public page', function () {
    beacon(['p' => '/en/news'], 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
    beacon(['p' => '/en/news'], VISITOR_AGENT, ['HTTP_DNT' => '1']);
    beacon(['p' => '/en/news'], VISITOR_AGENT, ['HTTP_SEC_GPC' => '1']);
    beacon(['p' => '/admin/login']);
    beacon(['p' => 'https://elsewhere.example/en']);
    beacon(['p' => '/fr/news']);
    beacon(['p' => '/en/<script>']);
    test()->call('POST', '/pv', [], [], [], ['HTTP_USER_AGENT' => VISITOR_AGENT], 'not json')->assertNoContent();

    expect(DB::table('page_view_days')->count())->toBe(0);

    beacon(['p' => '/am/news/%E1%8B%9C%E1%8A%93']);
    expect(DB::table('page_view_days')->value('path'))->toBe('/am/news/ዜና');
});

it('reports views by page, section, language, referrer, device and country with their share', function () {
    countVisit('/en', 'www.facebook.com', 400);
    countVisit('/en/news/sample-story', 'www.facebook.com', 1300);
    countVisit('/en/news/sample-story', '', 900, null);
    countVisit('/am/news/sample-story-am', 't.me', 1300, 'DE');

    $today = CarbonImmutable::now(EthiopianCalendar::timezone());
    $report = new TrafficReport($today->subDays(6), $today);

    expect($report->total())->toBe(4)
        ->and($report->daily())->toHaveCount(7)
        ->and(array_values($report->daily())[6])->toBe(4)
        ->and($report->rows('pages')[0])->toMatchArray(['label' => '/en/news/sample-story', 'locale' => 'en', 'views' => 2, 'share' => 50.0])
        ->and(array_column($report->rows('sections'), 'views', 'label'))->toBe(['news' => 3, __('analytics.value.home') => 1])
        ->and(array_column($report->rows('languages'), 'views', 'label'))->toBe(['English' => 3, 'አማርኛ' => 1])
        ->and(array_column($report->rows('referrers'), 'views', 'label'))->toBe(['facebook.com' => 2, __('analytics.value.direct') => 1, 't.me' => 1])
        ->and(array_column($report->rows('devices'), 'views', 'label'))->toBe([__('analytics.value.desktop') => 2, __('analytics.value.mobile') => 1, __('analytics.value.tablet') => 1])
        ->and(array_column($report->rows('countries'), 'views', 'label'))->toBe(['ET' => 2, 'DE' => 1, __('analytics.value.unknown') => 1])
        ->and((new TrafficReport($today->subDays(6), $today, 'am'))->total())->toBe(1);

    $csv = ReportCsv::make('pages', $report->rows('pages'));
    expect($csv)->toStartWith("\xEF\xBB\xBF")->toContain('/am/news/sample-story-am,am,1,25');
});

it('shows visits on the dashboard and in the report builder, with a CSV export', function () {
    countVisit('/en/news/sample-story', 'www.facebook.com', 400);
    countVisit('/en/news/sample-story');
    $this->actingAs(staff(SystemRole::Analyst));

    livewire(TrafficOverview::class)->assertSee(__('dashboard.traffic.views'))->assertSee(__('analytics.value.mobile'));
    livewire(TopPages::class)->assertSee('/en/news/sample-story');

    livewire(TrafficReports::class)
        ->assertOk()
        ->assertSee('/en/news/sample-story')
        ->filterTable('dimension', 'referrers')
        ->assertSee('facebook.com')
        ->filterTable('period', '7')
        ->assertSee(__('analytics.heading', ['total' => 2, 'period' => app(EthiopianCalendar::class)->format(CarbonImmutable::now(EthiopianCalendar::timezone())->subDays(6)).' – '.app(EthiopianCalendar::class)->format(CarbonImmutable::now(EthiopianCalendar::timezone()))]))
        ->callAction(TestAction::make('export')->table())
        ->assertFileDownloaded('traffic-referrers.csv');
});

it('emails a scheduled report on its day, a copy to each recipient', function () {
    Mail::fake();
    countVisit('/en/news/sample-story');
    $this->actingAs(staff(SystemRole::Analyst));

    livewire(TrafficReports::class)
        ->callAction(TestAction::make('schedule')->table(), data: [
            'name' => 'Sample weekly visits', 'dimension' => 'pages', 'frequency' => AnalyticsReport::WEEKLY,
            'recipients' => ['one@example.org', 'two@example.org'], 'mail_locale' => 'am',
        ])
        ->assertHasNoActionErrors();
    $report = AnalyticsReport::query()->sole();

    $monday = CarbonImmutable::parse('next monday 07:00', EthiopianCalendar::timezone());
    expect($report->isDue($monday))->toBeTrue()
        ->and($report->isDue($monday->addDay()))->toBeFalse()
        ->and($report->period($monday)[0]->toDateString())->toBe($monday->subDays(7)->toDateString());

    $this->travelTo($monday);
    $this->artisan('analytics:send-reports')->expectsOutputToContain('1 report(s) sent')->assertSuccessful();
    $this->artisan('analytics:send-reports')->expectsOutputToContain('0 report(s) sent');

    Mail::assertQueued(AnalyticsReportMail::class, 2);
    Mail::assertQueued(AnalyticsReportMail::class, fn (AnalyticsReportMail $mail): bool => $mail->hasTo('one@example.org') && ! $mail->hasTo('two@example.org')
        && $mail->locale === 'am' && $mail->name === 'Sample weekly visits');
    expect($report->refresh()->last_sent_at)->not->toBeNull();

    $monthly = new AnalyticsReport(['frequency' => AnalyticsReport::MONTHLY]);
    expect($monthly->isDue(CarbonImmutable::parse('2026-11-01')))->toBeTrue()
        ->and($monthly->period(CarbonImmutable::parse('2026-11-01'))[0]->toDateString())->toBe('2026-10-01');
});

it('keeps exports and scheduled reports to staff who may export', function () {
    $this->actingAs(staff(SystemRole::Publisher));

    livewire(TrafficReports::class)
        ->assertOk()
        ->assertActionHidden(TestAction::make('export')->table())
        ->assertActionHidden(TestAction::make('schedule')->table());
    $this->get(AnalyticsReportResource::getUrl())->assertForbidden();

    $this->actingAs(staff(SystemRole::Author))->get(TrafficReports::getUrl())->assertForbidden();
});

it('forgets daily counts after two years', function () {
    DB::table('page_view_days')->insert([
        ['date' => now()->subYears(2)->subDay()->toDateString(), 'locale' => 'en', 'path' => '/en', 'section' => 'home', 'views' => 5],
        ['date' => now()->subYear()->toDateString(), 'locale' => 'en', 'path' => '/en', 'section' => 'home', 'views' => 3],
    ]);

    $this->artisan('model:prune', ['--model' => [PageViewDay::class]])->assertSuccessful();

    expect(DB::table('page_view_days')->sum('views'))->toEqual(3);
});
