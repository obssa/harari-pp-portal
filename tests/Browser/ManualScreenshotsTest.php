<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Analytics\Actions\RecordPageView;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Filament\Pages\ManageTranslations;
use App\Filament\Pages\NotificationCentre;
use App\Filament\Pages\PrivacyRequests;
use App\Filament\Pages\SystemHealth;
use App\Filament\Pages\TrafficReports;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\SocialSources\SocialSourceResource;
use App\Filament\Resources\Submissions\SubmissionResource;
use Illuminate\Support\Facades\Artisan;

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
