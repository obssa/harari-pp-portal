<?php

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Domain\Engagement\Actions\SaveCampaign;
use App\Domain\Engagement\Actions\SendCampaign;
use App\Domain\Engagement\Actions\Subscribe;
use App\Domain\Engagement\Enums\CampaignStatus;
use App\Domain\Engagement\Jobs\DeliverCampaign;
use App\Domain\Engagement\Mail\CampaignMail;
use App\Domain\Engagement\Models\Campaign;
use App\Domain\Engagement\Models\CampaignDelivery;
use App\Domain\Engagement\Models\Subscriber;
use App\Domain\Engagement\Support\Audience;
use App\Domain\Engagement\Support\SmsLength;
use App\Domain\Membership\Actions\SaveMember;
use App\Domain\Membership\Models\Member;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\Campaigns\Pages\ViewCampaign;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Livewire\livewire;

/** A confirmed subscriber of the channel. */
function campaignSubscriber(string $locale, array $topics = ['news'], ?string $email = null, ?string $phone = null): Subscriber
{
    $subscriber = new Subscriber(['locale' => $locale, 'channels' => array_values(array_filter([$email ? 'email' : null, $phone ? 'sms' : null])), 'topics' => $topics, 'source' => 'form']);
    $subscriber->issueToken();
    $subscriber->forceFill(array_filter([
        'email' => $email, 'email_hash' => $email ? Subscriber::hash($email) : null, 'email_confirmed_at' => $email ? now() : null,
        'phone' => $phone, 'phone_hash' => $phone ? Subscriber::hash($phone) : null, 'phone_confirmed_at' => $phone ? now() : null,
    ]))->save();

    return $subscriber;
}

function emailCampaign(User $actor, array $audience = ['type' => 'subscribers'], array $content = []): Campaign
{
    return app(SaveCampaign::class)->handle(new Campaign, [
        'name' => 'Sample campaign', 'channel' => Campaign::EMAIL, 'audience' => $audience,
        'content' => $content ?: [
            'en' => ['subject' => 'Sample news this week', 'body' => "Sample **news** for readers.\n\n[Read more](https://hararipp.orcamind.dev/en/news)"],
            'am' => ['subject' => 'የናሙና ዜና', 'body' => 'የናሙና ዜና ለአንባቢዎች።'],
        ],
    ], $actor);
}

/** A gateway that keeps the messages instead of sending them. */
function capturingGateway(): object
{
    $gateway = new class implements SmsGateway
    {
        public array $sent = [];

        public function name(): string
        {
            return 'test';
        }

        public function send(string $to, string $text): void
        {
            $this->sent[] = [$to, $text];
        }
    };
    app()->instance(SmsGateway::class, $gateway);

    return $gateway;
}

beforeEach(fn () => Mail::fake());

it('writes drafts and checks the channel, the audience and the SMS length', function () {
    $officer = staff(SystemRole::CommunicationsOfficer);
    $campaign = emailCampaign($officer);
    expect($campaign->status)->toBe(CampaignStatus::Draft)->and($campaign->languages())->toBe(['en', 'am']);

    expect(fn () => emailCampaign($officer, ['type' => 'members']))->toThrow(ValidationException::class, __('campaigns.validation.audience'))
        ->and(fn () => app(SaveCampaign::class)->handle(new Campaign, ['name' => 'x', 'channel' => 'sms', 'audience' => ['type' => 'subscribers'],
            'content' => ['am' => ['text' => str_repeat('ሀ', 300)]]], $officer))->toThrow(ValidationException::class)
        ->and(fn () => emailCampaign(staff(SystemRole::Editor)))->toThrow(HttpException::class);

    expect(SmsLength::measure('Sample text'))->toBe(['characters' => 11, 'parts' => 1, 'unicode' => false])
        ->and(SmsLength::measure(str_repeat('a', 161))['parts'])->toBe(2)
        ->and(SmsLength::measure(str_repeat('ሀ', 71)))->toBe(['characters' => 71, 'parts' => 2, 'unicode' => true]);
});

it('reaches confirmed subscribers by topic and language, each address once', function () {
    $sender = staff(SystemRole::Admin);
    campaignSubscriber('en', ['news'], 'one@example.org');
    campaignSubscriber('am', ['events'], 'two@example.org');
    campaignSubscriber('har', ['news', 'events'], 'three@example.org');
    $gone = campaignSubscriber('en', ['news'], 'gone@example.org');
    app(Subscribe::class)->unsubscribe($gone);
    campaignSubscriber('en', ['news'], null, '+251911000001');

    $audience = new Audience($sender);
    expect($audience->count(emailCampaign($sender)))->toBe(3)
        ->and($audience->count(emailCampaign($sender, ['type' => 'subscribers', 'topics' => ['events']])))->toBe(2)
        ->and($audience->count(emailCampaign($sender, ['type' => 'subscribers', 'locales' => ['en']])))->toBe(1);
});

it('reaches members and volunteers only for staff who may see them, over their woredas, without those who opted out', function () {
    $north = app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'translations' => ['en' => ['name' => 'Sample North', 'published' => true]]], staff(SystemRole::Publisher));
    $south = app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'translations' => ['en' => ['name' => 'Sample South', 'published' => true]]], staff(SystemRole::Publisher));
    $member = fn (Office $w, string $phone): Member => app(SaveMember::class)->handle(new Member, ['name' => 'Sample', 'sex' => 'male', 'id_type' => 'none', 'phone' => $phone, 'joined_on' => '2025-01-01', 'woreda_id' => $w->id], staff(SystemRole::Admin));
    $member($north, '0911000011');
    $member($south, '0911000012');
    $out = $member($north, '0911000013');
    $out->forceFill(['campaigns_opt_out_at' => now()])->save();

    $officer = staff(SystemRole::MembershipOfficer);
    $officer->assignScopedRole(SystemRole::MembershipOfficer, ScopeType::Office, $north->id);
    $officer->givePermissionTo('campaigns.manage', 'campaigns.send');
    $sms = fn (User $actor, array $audience): Campaign => app(SaveCampaign::class)->handle(new Campaign, ['name' => 'x', 'channel' => 'sms', 'audience' => $audience,
        'content' => ['en' => ['text' => 'Sample meeting on Saturday']]], $actor);

    expect((new Audience($officer->refresh()))->count($sms($officer, ['type' => 'members'])))->toBe(1)
        ->and((new Audience(staff(SystemRole::Admin)))->count($sms(staff(SystemRole::Admin), ['type' => 'members', 'woredas' => [$south->id]])))->toBe(1)
        ->and((new Audience(staff(SystemRole::CommunicationsOfficer)))->allowed('members'))->toBeFalse();
});

it('sends an email campaign in each person\'s language with an unsubscribe link, skipping anyone who left meanwhile', function () {
    config(['portal.campaign_email_per_minute' => 2]);
    $sender = staff(SystemRole::Admin);
    campaignSubscriber('en', ['news'], 'one@example.org');
    campaignSubscriber('har', ['news'], 'three@example.org');
    $leaving = campaignSubscriber('en', ['news'], 'leaving@example.org');
    $campaign = emailCampaign($sender);

    Queue::fake();
    expect(app(SendCampaign::class)->start($campaign, $sender))->toBe(3);
    expect(json_encode(DB::table('campaign_deliveries')->get()))->not->toContain('one@example.org');
    app(Subscribe::class)->unsubscribe($leaving);

    (new DeliverCampaign($campaign->id))->handle(app(SendCampaign::class));
    expect($campaign->deliveries()->where('status', CampaignDelivery::QUEUED)->count())->toBe(1);
    Queue::assertPushed(DeliverCampaign::class);
    (new DeliverCampaign($campaign->id))->handle(app(SendCampaign::class));

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Sent)
        ->and($campaign->deliveries()->pluck('status', 'recipient_id')->sort()->values()->all())->toBe(['sent', 'sent', 'skipped']);
    Mail::assertSent(CampaignMail::class, 2);
    Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail): bool => $mail->hasTo('three@example.org') && $mail->language === 'am'
        && str_contains((string) $mail->headers()->text['List-Unsubscribe'], '/har/u/'));
    $mail = new CampaignMail($campaign, 'en', $campaign->deliveries()->first());
    $mail->assertSeeInHtml('news</strong>', false)->assertSeeInHtml(__('campaigns.mail.unsubscribe', [], 'en'), false);
});

it('sends SMS campaigns with a stop link that opts the person out', function () {
    $gateway = capturingGateway();
    $sender = staff(SystemRole::Admin);
    $subscriber = campaignSubscriber('en', ['news'], null, '+251911000021');
    $campaign = app(SaveCampaign::class)->handle(new Campaign, ['name' => 'x', 'channel' => 'sms', 'audience' => ['type' => 'subscribers'],
        'content' => ['en' => ['text' => 'Sample meeting on Saturday at 9.']]], $sender);

    app(SendCampaign::class)->start($campaign, $sender);
    $delivery = $campaign->deliveries()->sole();
    expect($gateway->sent)->toHaveCount(1)
        ->and($gateway->sent[0][0])->toBe('+251911000021')
        ->and($gateway->sent[0][1])->toContain('Sample meeting')->toContain("/en/u/{$delivery->token}");

    $this->get("/en/u/{$delivery->token}")->assertOk()->assertSee(__('campaigns.unsubscribe.ask'))->assertCookieMissing(config('session.cookie'));
    $this->post("/en/u/{$delivery->token}")->assertOk()->assertSee(__('campaigns.unsubscribe.done'));
    expect($subscriber->refresh()->unsubscribed_at)->not->toBeNull()->and($delivery->refresh()->unsubscribed_at)->not->toBeNull();
    $this->get('/en/u/aaaaaaaaaaaa')->assertNotFound();
});

it('schedules, starts on time, goes back to draft and stops', function () {
    $sender = staff(SystemRole::Admin);
    campaignSubscriber('en', ['news'], 'one@example.org');
    $campaign = emailCampaign($sender);

    expect(fn () => app(SendCampaign::class)->schedule($campaign, CarbonImmutable::now()->subHour(), $sender))->toThrow(ValidationException::class);
    app(SendCampaign::class)->schedule($campaign, CarbonImmutable::now()->addHour(), $sender);
    $this->artisan('campaigns:dispatch')->assertSuccessful();
    expect($campaign->refresh()->status)->toBe(CampaignStatus::Scheduled);

    app(SendCampaign::class)->cancel($campaign, $sender);
    expect($campaign->refresh()->status)->toBe(CampaignStatus::Draft);

    app(SendCampaign::class)->schedule($campaign, CarbonImmutable::now()->addMinute(), $sender);
    CarbonImmutable::setTestNow(CarbonImmutable::now()->addMinutes(2));
    $this->artisan('campaigns:dispatch')->assertSuccessful();
    CarbonImmutable::setTestNow();
    expect($campaign->refresh()->status)->toBe(CampaignStatus::Sent)
        ->and(fn () => app(SaveCampaign::class)->handle($campaign, ['name' => 'Changed'], $sender))->toThrow(ValidationException::class);
    Mail::assertSent(CampaignMail::class, 1);
});

it('sends a test to the sender only and runs from the admin screens', function () {
    $officer = staff(SystemRole::CommunicationsOfficer, attributes: ['email' => 'officer@example.org']);
    campaignSubscriber('en', ['news'], 'one@example.org');
    $campaign = emailCampaign($officer);
    $this->actingAs($officer);

    livewire(ViewCampaign::class, ['record' => $campaign->getRouteKey()])
        ->callAction('test', ['locale' => 'am'])->assertHasNoActionErrors()
        ->callAction('send')->assertHasNoActionErrors();
    Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail): bool => $mail->hasTo('officer@example.org') && $mail->delivery === null && $mail->language === 'am');
    Mail::assertSent(CampaignMail::class, fn (CampaignMail $mail): bool => $mail->hasTo('one@example.org'));
    expect($campaign->refresh()->status)->toBe(CampaignStatus::Sent);

    $this->get(CampaignResource::getUrl('edit', ['record' => $campaign]))->assertForbidden();
    $this->get(CampaignResource::getUrl('view', ['record' => $campaign]))->assertOk()->assertSee('Sample news this week');
    $this->actingAs(staff(SystemRole::Editor))->get(CampaignResource::getUrl('index'))->assertForbidden();
});
