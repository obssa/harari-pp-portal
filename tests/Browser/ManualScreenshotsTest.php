<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Analytics\Actions\RecordPageView;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Engagement\Actions\SaveCampaign;
use App\Domain\Engagement\Actions\SendCampaign;
use App\Domain\Engagement\Models\Campaign;
use App\Domain\Engagement\Models\Subscriber;
use App\Domain\Membership\Actions\SaveMember;
use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Domain\Press\Actions\SaveMediaContact;
use App\Domain\Press\Models\MediaContact;
use App\Domain\Volunteers\Actions\ManageVolunteer;
use App\Domain\Volunteers\Actions\SaveVolunteer;
use App\Filament\Pages\ManageTranslations;
use App\Filament\Pages\MembershipReports;
use App\Filament\Pages\NotificationCentre;
use App\Filament\Pages\PrivacyRequests;
use App\Filament\Pages\SystemHealth;
use App\Filament\Pages\TrafficReports;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\MediaContacts\MediaContactResource;
use App\Filament\Resources\MemberImports\MemberImportResource;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\MembershipApplications\MembershipApplicationResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\SocialSources\SocialSourceResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Resources\Subscribers\SubscriberResource;
use App\Filament\Resources\Volunteers\VolunteerResource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

/*
| Screenshots for the admin manual (step 5.10). Not a test: it runs only
| with MANUAL_SCREENSHOTS=1 and writes PNG files under
| tests/Browser/Screenshots/manual-*, which are copied to docs/manual/images.
|
|   MANUAL_SCREENSHOTS=1 vendor/bin/pest tests/Browser/ManualScreenshotsTest.php
*/

require_once __DIR__.'/BrowserSupport.php';

beforeEach(function () {
    if (getenv('MANUAL_SCREENSHOTS') !== '1') {
        $this->markTestSkipped('Only for the manual: MANUAL_SCREENSHOTS=1.');
    }
    $this->withVite();
    browserContent();
});

it('captures the admin screens for the manual', function (string $locale) {
    $admin = staff(SystemRole::SuperAdmin, mfa: false, attributes: ['name' => 'Sample Administrator', 'locale' => $locale]);
    $draft = app(SavePost::class)->handle(new Post, ['type' => 'news', 'translations' => [
        'en' => ['title' => 'Sample council meeting reviews the plan', 'summary' => 'A sample story waiting to be published.'],
    ]], $admin);
    foreach (['/en', '/en/news/sample-browser-story', '/am', '/en/events'] as $path) {
        app(RecordPageView::class)->handle(['p' => $path, 'r' => 'www.facebook.com', 'w' => 400], 'Mozilla/5.0 Chrome/141.0', 'ET', null);
    }
    Artisan::call('health:check', ['--no-notification' => true]);

    $this->actingAs($admin);
    $path = fn (string $url): string => (string) parse_url($url, PHP_URL_PATH);
    $screens = [
        'dashboard' => '/admin',
        'posts' => $path(PostResource::getUrl('index')),
        'post-edit' => $path(PostResource::getUrl('edit', ['record' => $draft])),
        'translations' => $path(ManageTranslations::getUrl()),
        'notifications' => $path(NotificationCentre::getUrl()),
        'inbox' => $path(SubmissionResource::getUrl('index')),
        'social' => $path(SocialSourceResource::getUrl('index')),
        'health' => $path(SystemHealth::getUrl()),
        'traffic' => $path(TrafficReports::getUrl()),
        'privacy' => $path(PrivacyRequests::getUrl()),
    ];

    foreach ($screens as $name => $url) {
        visit($url)->resize(1440, 900)->wait(1)->screenshot(fullPage: false, filename: "manual-{$locale}-{$name}");
    }

    expect(true)->toBeTrue();
})->with(['en', 'am']);

it('captures the membership and volunteer screens for the manual', function (string $locale) {
    $admin = staff(SystemRole::SuperAdmin, mfa: false, attributes: ['name' => 'Sample Administrator', 'locale' => $locale]);
    $woreda = app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'translations' => ['en' => ['name' => 'Sample Woreda 1', 'published' => true]]], $admin);
    $application = new MembershipApplication(['locale' => 'en', 'name' => 'Sample Applicant Person', 'sex' => 'female', 'birth_date' => '1994-05-12', 'birth_year' => 1994,
        'id_type' => 'fayda', 'id_number' => '123456789012', 'education' => 'degree', 'occupation' => 'Sample teacher', 'woreda_id' => $woreda->id,
        'phone' => '+251911000111', 'phone_hash' => MembershipApplication::hash('+251911000111')]);
    $application->forceFill(['reference' => MembershipApplication::newReference(), 'status' => ApplicationStatus::Submitted, 'submitted_at' => now(), 'phone_verified_at' => now()])->save();
    $member = app(SaveMember::class)->handle(new Member, ['name' => 'Sample Member Person', 'sex' => 'male', 'birth_date' => '1985-02-01', 'id_type' => 'kebele', 'id_number' => 'KB123456',
        'phone' => '0911000222', 'woreda_id' => $woreda->id, 'joined_on' => '2025-09-11'], $admin);
    $volunteer = app(SaveVolunteer::class)->signUp(['name' => 'Sample Volunteer Person', 'phone' => '0911000333', 'woreda_id' => $woreda->id,
        'skills' => ['ushering', 'first_aid'], 'availability' => ['weekends'], 'languages' => ['har', 'am'], 'consent' => '1'], 'en');
    app(ManageVolunteer::class)->saveAssignment($volunteer, null, ['task' => 'Sample ushering at the forum', 'on_date' => now()->toDateString(), 'status' => 'done', 'hours' => '3'], $admin);

    $this->actingAs($admin);
    $path = fn (string $url): string => (string) parse_url($url, PHP_URL_PATH);
    $screens = [
        'applications' => $path(MembershipApplicationResource::getUrl('index')),
        'application' => $path(MembershipApplicationResource::getUrl('view', ['record' => $application])),
        'members' => $path(MemberResource::getUrl('index')),
        'member' => $path(MemberResource::getUrl('view', ['record' => $member])),
        'imports' => $path(MemberImportResource::getUrl('index')),
        'volunteers' => $path(VolunteerResource::getUrl('view', ['record' => $volunteer])),
        'membership-report' => $path(MembershipReports::getUrl()),
    ];
    foreach ($screens as $name => $url) {
        visit($url)->resize(1440, 900)->wait(1)->screenshot(fullPage: false, filename: "manual-{$locale}-{$name}");
    }

    $this->actingAs($member, 'member');
    visit("/{$locale}/member/card")->resize(1280, 800)->wait(1)->screenshot(fullPage: false, filename: "manual-{$locale}-member-card");
    visit("/{$locale}/volunteer")->resize(1280, 800)->wait(1)->screenshot(fullPage: false, filename: "manual-{$locale}-volunteer-form");

    expect(true)->toBeTrue();
})->with(['en', 'am']);

it('captures the communication screens for the manual', function (string $locale) {
    $admin = staff(SystemRole::SuperAdmin, mfa: false, attributes: ['name' => 'Sample Administrator', 'locale' => $locale]);
    foreach (['reader.one@example.org' => 'en', 'reader.two@example.org' => 'am', 'reader.three@example.org' => 'om'] as $email => $language) {
        $subscriber = new Subscriber(['locale' => $language, 'channels' => ['email'], 'topics' => ['news', 'events'], 'source' => 'form']);
        $subscriber->issueToken();
        $subscriber->forceFill(['email' => $email, 'email_hash' => Subscriber::hash($email), 'email_confirmed_at' => now()])->save();
    }
    $campaign = app(SaveCampaign::class)->handle(new Campaign, [
        'name' => 'Sample monthly news', 'channel' => Campaign::EMAIL, 'audience' => ['type' => 'subscribers', 'topics' => ['news']],
        'content' => [
            'en' => ['subject' => 'Sample: news from the branch office', 'body' => "This is **sample** text.\n\n[Read the news](https://hararipp.orcamind.dev/en/news)"],
            'am' => ['subject' => 'ናሙና፦ ከቅርንጫፍ ጽሕፈት ቤቱ ዜና', 'body' => 'ይህ **የናሙና** ጽሑፍ ነው።'],
        ],
    ], $admin);
    $draft = app(SaveCampaign::class)->handle(new Campaign, [
        'name' => 'Sample SMS reminder', 'channel' => Campaign::SMS, 'audience' => ['type' => 'subscribers'],
        'content' => ['en' => ['text' => 'Sample: the public forum is on Saturday at 9.']],
    ], $admin);
    Mail::fake();
    app(SendCampaign::class)->start($campaign, $admin);
    app(SaveMediaContact::class)->handle(new MediaContact, ['name' => 'Sample Journalist', 'outlet' => 'Sample Radio', 'position' => 'News editor',
        'email' => 'desk@example.org', 'languages' => ['am', 'en']], $admin);

    $this->actingAs($admin);
    $path = fn (string $url): string => (string) parse_url($url, PHP_URL_PATH);
    $screens = [
        'subscribers' => $path(SubscriberResource::getUrl('index')),
        'campaign-edit' => $path(CampaignResource::getUrl('edit', ['record' => $draft])),
        'campaign-report' => $path(CampaignResource::getUrl('view', ['record' => $campaign])),
        'media-contacts' => $path(MediaContactResource::getUrl('index')),
    ];
    foreach ($screens as $name => $url) {
        visit($url)->resize(1440, 900)->wait(1)->screenshot(fullPage: false, filename: "manual-{$locale}-{$name}");
    }
    visit("/{$locale}/newsletter")->resize(1280, 800)->wait(1)->screenshot(fullPage: false, filename: "manual-{$locale}-newsletter-form");

    expect(true)->toBeTrue();
})->with(['en', 'am']);
