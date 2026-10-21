<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Engagement\Actions\SaveCampaign;
use App\Domain\Engagement\Actions\SendCampaign;
use App\Domain\Engagement\Mail\CampaignMail;
use App\Domain\Engagement\Models\Campaign;
use App\Domain\Engagement\Models\Subscriber;
use App\Domain\Engagement\Support\CampaignReport;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Campaigns\Pages\ViewCampaign;
use App\Filament\Widgets\CampaignResults;
use App\Filament\Widgets\SubscriberGrowth;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

function reportSubscriber(string $email, string $locale = 'en'): Subscriber
{
    $subscriber = new Subscriber(['locale' => $locale, 'channels' => ['email'], 'topics' => ['news'], 'source' => 'form']);
    $subscriber->issueToken();
    $subscriber->forceFill(['email' => $email, 'email_hash' => Subscriber::hash($email), 'email_confirmed_at' => now()])->save();

    return $subscriber;
}

function sentCampaign(User $sender): Campaign
{
    $campaign = app(SaveCampaign::class)->handle(new Campaign, [
        'name' => 'Sample report campaign', 'channel' => Campaign::EMAIL, 'audience' => ['type' => 'subscribers'],
        'content' => [
            'en' => ['subject' => 'Sample subject', 'body' => 'Read [the story](https://hararipp.orcamind.dev/en/news/sample) and [the events](https://example.org/events?a=1&b=2).'],
            'am' => ['subject' => 'የናሙና ርዕስ', 'body' => 'ናሙና [ዜና](https://hararipp.orcamind.dev/am/news)'],
        ],
    ], $sender);
    app(SendCampaign::class)->start($campaign, $sender);

    return $campaign->refresh();
}

beforeEach(fn () => Mail::fake());

it('counts clicks through the portal\'s own links and sends people on to the address the campaign gave', function () {
    reportSubscriber('one@example.org');
    $campaign = sentCampaign(staff(SystemRole::Admin));
    $delivery = $campaign->deliveries()->sole();

    $html = (new CampaignMail($campaign, 'en', $delivery))->render();
    expect($html)->toContain("/en/c/{$delivery->token}/0")->toContain("/en/c/{$delivery->token}/1")
        ->not->toContain('https://example.org/events')->not->toContain('/o/');

    $this->get("/en/c/{$delivery->token}/1")->assertRedirect('https://example.org/events?a=1&b=2')->assertCookieMissing(config('session.cookie'));
    $this->get("/en/c/{$delivery->token}/0")->assertRedirect('https://hararipp.orcamind.dev/en/news/sample');
    $this->get("/en/c/{$delivery->token}/7")->assertNotFound();
    $this->get('/en/c/aaaaaaaaaaaa/0')->assertNotFound();

    expect($delivery->refresh()->clicks)->toBe(2)->and($delivery->clicked_at)->not->toBeNull();
});

it('counts opens only when switched on', function () {
    reportSubscriber('one@example.org');
    $campaign = sentCampaign(staff(SystemRole::Admin));
    $delivery = $campaign->deliveries()->sole();

    config(['portal.campaign_open_tracking' => true]);
    expect((new CampaignMail($campaign, 'en', $delivery))->render())->toContain("/en/o/{$delivery->token}.gif");
    $this->get("/en/o/{$delivery->token}.gif")->assertOk()->assertHeader('Content-Type', 'image/gif');
    expect($delivery->refresh()->opened_at)->not->toBeNull();
});

it('reports what happened, by language, without any address', function () {
    reportSubscriber('one@example.org');
    reportSubscriber('two@example.org', 'am');
    $campaign = sentCampaign(staff(SystemRole::Admin));
    $first = $campaign->deliveries()->where('locale', 'en')->sole();
    $this->get("/en/c/{$first->token}/0");
    $this->post("/en/u/{$first->token}");

    $report = new CampaignReport($campaign);
    expect($report->totals())->toMatchArray(['recipients' => 2, 'sent' => 2, 'failed' => 0, 'clicked' => 1, 'clicks' => 1, 'unsubscribed' => 1])
        ->and(CampaignReport::rate(1, 2))->toBe(50.0)
        ->and(collect($report->byLanguage())->pluck('sent', 'language')->all())->toEqual(['en' => 1, 'am' => 1]);

    $out = fopen('php://memory', 'w+b');
    $report->write($out);
    rewind($out);
    $csv = (string) stream_get_contents($out);
    expect($csv)->toContain(__('campaigns.report.type.subscriber'))->not->toContain('one@example.org')->not->toContain($first->token);

    $this->actingAs(staff(SystemRole::CommunicationsOfficer));
    $this->get(CampaignResource::getUrl('view', ['record' => $campaign]))->assertOk()->assertSee(__('campaigns.report.title'))->assertSee('1 (50%)');
    livewire(ViewCampaign::class, ['record' => $campaign->getRouteKey()])->callAction('report')->assertFileDownloaded("campaign-{$campaign->id}-report.csv");
});

it('shows campaign results and subscribers on the dashboard', function () {
    reportSubscriber('one@example.org');
    sentCampaign(staff(SystemRole::Admin));
    $this->actingAs(staff(SystemRole::CommunicationsOfficer));

    livewire(CampaignResults::class, ['pageFilters' => ['range' => '30']])->assertSee('Sample report campaign');
    livewire(SubscriberGrowth::class, ['pageFilters' => ['range' => '30']])
        ->assertSeeInOrder([__('campaigns.dashboard.by_email'), '1', __('campaigns.dashboard.by_sms'), '0']);
});
