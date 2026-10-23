<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Analytics\Actions\RecordPageView;
use App\Domain\Content\Actions\LoadContentPack;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Domain\Engagement\Models\Campaign;
use App\Domain\Engagement\Models\Subscriber;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Models\Media;
use App\Domain\Membership\Actions\SaveMember;
use App\Domain\Membership\Enums\ApplicationStatus;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\MembershipApplication;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Domain\Volunteers\Actions\SaveVolunteer;
use App\Filament\Pages\ManageHomepage;
use App\Filament\Pages\ManageMembershipSettings;
use App\Filament\Pages\ManageSecurity;
use App\Filament\Pages\ManageSiteSettings;
use App\Filament\Pages\ManageTranslations;
use App\Filament\Pages\NotificationCentre;
use App\Filament\Pages\SystemHealth;
use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Campaigns\CampaignResource;
use App\Filament\Resources\ContentSources\ContentSourceResource;
use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Leaders\LeaderResource;
use App\Filament\Resources\Leagues\LeagueResource;
use App\Filament\Resources\Media\MediaResource;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\MembershipApplications\MembershipApplicationResource;
use App\Filament\Resources\Menus\MenuResource;
use App\Filament\Resources\Offices\OfficeResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use App\Filament\Resources\Subscribers\SubscriberResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Volunteers\VolunteerResource;
use App\Support\HararPhotos;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

require_once __DIR__.'/BrowserSupport.php';

/*
 * Screens for the staff manual (docs/manual, the published "Staff Manual" page). Run on purpose only:
 * MANUAL_SCREENSHOTS=1 vendor/bin/pest tests/Browser/StaffManualScreenshotsTest.php
 * Files land in tests/Browser/Screenshots/staff-*.png.
 */
beforeEach(function () {
    if (getenv('MANUAL_SCREENSHOTS') !== '1') {
        $this->markTestSkipped('Only for the manual: MANUAL_SCREENSHOTS=1.');
    }
    $this->withVite();
});

it('captures the screens of the staff manual', function () {
    browserContent();
    $admin = staff(SystemRole::SuperAdmin, mfa: false, attributes: ['name' => 'Sample Administrator', 'email' => 'admin@example.org', 'locale' => 'en']);
    $author = staff(SystemRole::Author, mfa: false, attributes: ['name' => 'Sample Author']);
    $editor = staff(SystemRole::Editor, mfa: false, attributes: ['name' => 'Sample Editor']);
    $publisher = staff(SystemRole::Publisher, mfa: false, attributes: ['name' => 'Sample Publisher']);
    staff(SystemRole::MembershipOfficer, mfa: false, attributes: ['name' => 'Sample Membership Officer']);
    staff(SystemRole::CommunicationsOfficer, mfa: false, attributes: ['name' => 'Sample Communications Officer']);
    staff(SystemRole::Translator, mfa: false, attributes: ['name' => 'Sample Translator']);
    app(LoadContentPack::class)->handle(database_path('content/pack.json'), $admin);

    // Photos of Harar from the site's own files, uploaded like any picture.
    $photo = fn (string $name): Media => tap(app(UploadMedia::class)->handle(public_path(ltrim(HararPhotos::fallback($name), '/')), "harar-{$name}.jpg", $admin), function (Media $media) use ($name): void {
        $media->forceFill(['alt' => ['en' => __("harar.photo.{$name}", [], 'en')], 'credit' => 'Wikimedia Commons'])->save();
    });
    $gate = $photo('gate');
    $mosque = $photo('mosque');
    $photo('alley');

    // Stories at each step of the workflow.
    $story = fn (string $title, ?Media $image, $by): Post => app(SavePost::class)->handle(new Post, ['type' => 'news', 'featured_image_id' => $image?->id, 'translations' => [
        'en' => ['title' => $title, 'excerpt' => 'Sample summary of the story, one or two sentences long.',
            'body' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Sample text of the story. Write in short paragraphs; add photos with the image block.']]]]]],
        'am' => ['title' => 'የናሙና ዜና ርዕስ', 'excerpt' => 'የናሙና ማጠቃለያ።'],
    ]], $by);
    $draft = $story('Sample: youth volunteers clean Jugol streets', $mosque, $author);
    $inReview = $story('Sample: woreda offices review the first quarter', $gate, $author);
    app(ContentWorkflow::class)->apply($inReview, 'en', Transition::Submit, $author);
    $published = $story('Sample: Harari Tourism Day opens in Harar', $gate, $publisher);
    foreach (['en', 'am'] as $locale) {
        app(ContentWorkflow::class)->apply($published, $locale, Transition::Publish, $publisher);
    }

    // Membership, volunteers, messages, subscribers.
    $woreda = Office::query()->where('type', 'woreda')->firstOrFail();
    $application = new MembershipApplication(['locale' => 'en', 'name' => 'Sample Applicant Person', 'sex' => 'female', 'birth_date' => '1994-05-12', 'birth_year' => 1994,
        'id_type' => 'fayda', 'id_number' => '123456789012', 'education' => 'degree', 'occupation' => 'Sample teacher', 'woreda_id' => $woreda->id,
        'phone' => '+251911000111', 'phone_hash' => MembershipApplication::hash('+251911000111')]);
    $application->forceFill(['reference' => MembershipApplication::newReference(), 'status' => ApplicationStatus::Submitted, 'submitted_at' => now(), 'phone_verified_at' => now()])->save();
    $member = app(SaveMember::class)->handle(new Member, ['name' => 'Sample Member Person', 'sex' => 'male', 'birth_date' => '1985-02-01', 'id_type' => 'kebele', 'id_number' => 'KB123456',
        'phone' => '0911000222', 'woreda_id' => $woreda->id, 'joined_on' => '2025-09-11'], $admin);
    app(SaveVolunteer::class)->signUp(['name' => 'Sample Volunteer Person', 'phone' => '0911000333', 'woreda_id' => $woreda->id,
        'skills' => ['ushering', 'first_aid'], 'availability' => ['weekends'], 'languages' => ['har', 'am'], 'consent' => '1'], 'en');
    foreach (['news', 'events'] as $i => $topic) {
        $subscriber = new Subscriber(['locale' => 'en', 'channels' => ['email'], 'topics' => [$topic], 'source' => 'form']);
        $subscriber->issueToken();
        $email = "reader{$i}@example.org";
        $subscriber->forceFill(['email' => $email, 'email_hash' => Subscriber::hash($email), 'email_confirmed_at' => now()])->save();
    }
    $campaign = Campaign::query()->create(['name' => 'Sample monthly news', 'channel' => Campaign::EMAIL, 'audience' => ['type' => 'subscribers', 'topics' => ['news']],
        'content' => ['en' => ['subject' => 'Sample: news from the branch office', 'body' => "This is **sample** text.\n\n[Read the news](https://hararipp.orcamind.dev/en/news)"]]]);
    foreach (['/en', '/en/leaders', '/am', '/en/about', '/en/news'] as $path) {
        app(RecordPageView::class)->handle(['p' => $path, 'r' => 'www.facebook.com', 'w' => 400], 'Mozilla/5.0 Chrome/141.0', 'ET', null);
    }
    Artisan::call('health:check', ['--no-notification' => true]);

    $leader = Leader::query()->whereHas('translations', fn ($q) => $q->where('name', 'H.E Getu Weyessa Tola'))->firstOrFail();
    $league = League::query()->whereHas('translations', fn ($q) => $q->where('name', "Women's Wing"))->firstOrFail();
    $about = Page::query()->whereHas('translations', fn ($q) => $q->where('slug', 'about'))->firstOrFail();
    $path = fn (string $url): string => (string) parse_url($url, PHP_URL_PATH);
    $shot = fn (string $url, string $name, bool $full = false) => visit($url)->resize(1440, 900)->wait(1.5)->screenshot(fullPage: $full, filename: "staff-{$name}");

    // Signing in, before anyone is signed in.
    $shot('/admin/login', 'login');
    $shot('/en/membership/apply', 'public-join');

    $this->actingAs($admin);
    $screens = [
        'dashboard' => ['/admin', true],
        'profile' => ['/admin/profile', false],
        'posts' => [$path(PostResource::getUrl('index')), false],
        'post-create' => [$path(PostResource::getUrl('create')), true],
        'post-review' => [$path(PostResource::getUrl('edit', ['record' => $inReview])), true],
        'post-published' => [$path(PostResource::getUrl('edit', ['record' => $published])), false],
        'pages' => [$path(PageResource::getUrl('index')), false],
        'page-edit' => [$path(PageResource::getUrl('edit', ['record' => $about])), false],
        'menus' => [$path(MenuResource::getUrl('index')), false],
        'homepage' => [$path(ManageHomepage::getUrl()), false],
        'leaders' => [$path(LeaderResource::getUrl('index')), false],
        'leader-edit' => [$path(LeaderResource::getUrl('edit', ['record' => $leader])), true],
        'offices' => [$path(OfficeResource::getUrl('index')), false],
        'league-edit' => [$path(LeagueResource::getUrl('edit', ['record' => $league])), false],
        'media' => [$path(MediaResource::getUrl('index')), false],
        'media-edit' => [$path(MediaResource::getUrl('edit', ['record' => $gate])), false],
        'events' => [$path(EventResource::getUrl('index')), false],
        'documents' => [$path(DocumentResource::getUrl('index')), false],
        'membership-settings' => [$path(ManageMembershipSettings::getUrl()), false],
        'applications' => [$path(MembershipApplicationResource::getUrl('index')), false],
        'application' => [$path(MembershipApplicationResource::getUrl('view', ['record' => $application])), false],
        'members' => [$path(MemberResource::getUrl('index')), false],
        'member' => [$path(MemberResource::getUrl('view', ['record' => $member])), false],
        'volunteers' => [$path(VolunteerResource::getUrl('index')), false],
        'inbox' => [$path(SubmissionResource::getUrl('index')), false],
        'subscribers' => [$path(SubscriberResource::getUrl('index')), false],
        'campaign' => [$path(CampaignResource::getUrl('edit', ['record' => $campaign])), false],
        'translations' => [$path(ManageTranslations::getUrl()), false],
        'users' => [$path(UserResource::getUrl('index')), false],
        'role' => [$path(RoleResource::getUrl('edit', ['record' => Role::findByName(SystemRole::Editor->value)])), false],
        'site-settings' => [$path(ManageSiteSettings::getUrl()), false],
        'security' => [$path(ManageSecurity::getUrl()), false],
        'sources' => [$path(ContentSourceResource::getUrl('index')), false],
        'audit' => [$path(ActivityResource::getUrl('index')), false],
        'health' => [$path(SystemHealth::getUrl()), false],
        'notifications' => [$path(NotificationCentre::getUrl()), false],
    ];
    foreach ($screens as $name => [$url, $full]) {
        $shot($url, $name, $full);
    }

    // What the public sees.
    $shot('/en', 'public-home');
    $shot('/en/news', 'public-news');

    // An author sees only their own tools.
    $this->actingAs($author);
    $shot('/admin', 'author-dashboard');
    $this->actingAs($editor);
    $shot($path(PostResource::getUrl('edit', ['record' => $inReview])), 'editor-review');

    expect(true)->toBeTrue();
});
