<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Models\Media;
use App\Domain\Organisation\Actions\SaveLeader;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Social\Enums\Platform;
use App\Domain\Social\Models\SocialSource;
use App\Settings\SiteSettings;

/**
 * @param  array<string, string>  $links
 */
function socialSettings(array $links, string $feed = 'ask'): void
{
    $settings = app(SiteSettings::class);
    $settings->social_links = $links;
    $settings->facebook_feed = $feed;
    $settings->save();
}

function galleryPicture(string $name): Media
{
    $path = storage_path('framework/testing/'.$name.'-'.uniqid().'.jpg');
    @mkdir(dirname($path), 0777, true);
    $image = imagecreatetruecolor(900, 600);
    imagefill($image, 0, 0, imagecolorallocate($image, 5, 37, 59));
    imagejpeg($image, $path, 80);
    $media = app(UploadMedia::class)->handle($path, "{$name}.jpg", staff(SystemRole::Publisher, mfa: false));
    $media->forceFill(['alt' => ['en' => "Sample {$name} photo"]])->save();

    return $media;
}

it('shows the party\'s Facebook page beside its Telegram channel, loading Facebook only once the visitor asks', function () {
    socialSettings(['facebook' => 'https://www.facebook.com/profile.php?id=100064354104698', 'telegram' => 'https://t.me/hrprosperityparty', 'youtube' => 'https://www.youtube.com/@sample']);

    $html = (string) $this->get('/en')->assertOk()->getContent();
    expect($html)->toContain('srcdoc=', __('social.facebook.show', [], 'en'))
        ->and($html)->toContain(e('https://www.facebook.com/plugins/page.php?href='.urlencode('https://www.facebook.com/profile.php?id=100064354104698')))
        ->and($html)->not->toContain('<iframe src="https://www.facebook.com/plugins')
        ->and($html)->toContain('href="https://t.me/hrprosperityparty"', 'href="https://t.me/s/hrprosperityparty"', __('social.telegram.latest', [], 'en'))
        ->and($html)->toContain('href="https://www.youtube.com/@sample"', '@hrprosperityparty', '@sample', __('social.action.subscribe', [], 'en'))
        ->and(substr_count($html, 'href="https://www.facebook.com/profile.php?id=100064354104698"'))->toBeGreaterThan(1)
        ->and($html)->toContain(__('social.facebook.slow', [], 'en'));

    expect($this->get('/en')->headers->get('Content-Security-Policy'))->toContain('frame-src', 'https://www.facebook.com', 'https://web.facebook.com');

    socialSettings(['facebook' => 'https://www.facebook.com/profile.php?id=100064354104698'], 'auto');
    expect((string) $this->get('/en')->getContent())->toContain('<iframe src="https://www.facebook.com/plugins/page.php?');
    $this->get('/en/social')->assertOk()->assertSee('<iframe src="https://www.facebook.com/plugins/page.php?', escape: false);

    socialSettings(['facebook' => 'https://www.facebook.com/profile.php?id=100064354104698'], 'off');
    expect((string) $this->get('/en')->getContent())->not->toContain('facebook.com/plugins')
        ->toContain('href="https://www.facebook.com/profile.php?id=100064354104698"');
});

it('loads the Facebook posts at once unless an administrator chose otherwise', function () {
    $settings = app(SiteSettings::class);
    $settings->social_links = ['facebook' => 'https://www.facebook.com/profile.php?id=100064354104698'];
    $settings->save();

    expect($settings->facebook_feed)->toBe('auto')
        ->and((string) $this->get('/en')->getContent())->toContain('<iframe src="https://www.facebook.com/plugins/page.php?');
});

it('shows the latest Telegram posts as the server read them, with their pictures on this site', function () {
    socialSettings(['telegram' => 'https://t.me/hrprosperityparty']);
    $source = SocialSource::query()->create(['platform' => Platform::Telegram, 'handle' => 'hrprosperityparty', 'name' => 'Sample channel']);
    foreach ([1, 2, 3, 4, 5] as $n) {
        $source->posts()->create(['external_id' => (string) $n, 'posted_at' => now()->subHours(6 - $n), 'text' => "Sample channel post {$n}", 'permalink' => "https://t.me/hrprosperityparty/{$n}"]);
    }

    $this->get('/en')->assertOk()
        ->assertSeeInOrder(['Sample channel post 5', 'Sample channel post 4', 'Sample channel post 3', 'Sample channel post 2'])
        ->assertDontSee('Sample channel post 1')
        ->assertSee('href="https://t.me/hrprosperityparty/5"', escape: false)
        ->assertSee(__('social.telegram.read', [], 'en'))
        ->assertSee(__('social.telegram.all', [], 'en'));
});

it('lets administrators choose how the Facebook posts load', function () {
    $this->actingAs(staff(SystemRole::SuperAdmin, mfa: false));

    $this->get('/admin/settings/site')->assertOk()->assertSee(__('admin.settings.site.field.facebook_feed'))
        ->assertSee(__('admin.settings.site.facebook_feed.ask'));
});

it('shows a gallery of photos on a leader\'s profile, in the chosen order, each opening large', function () {
    $first = galleryPicture('office');
    $second = galleryPicture('emblem');
    $leader = app(SaveLeader::class)->handle(new Leader, [
        'body' => 'secretariat',
        'photo_ids' => [$second->id, $first->id, $second->id, 999999],
        'translations' => ['en' => ['name' => 'Sample Gallery Leader', 'published' => true]],
    ], staff(SystemRole::Publisher, mfa: false));

    expect($leader->photo_ids)->toBe([$second->id, $first->id])
        ->and($leader->photos()->pluck('id')->all())->toBe([$second->id, $first->id]);

    $this->get('/en/leaders/sample-gallery-leader')->assertOk()
        ->assertSee(__('leaders.photos', [], 'en'))
        ->assertSeeInOrder(['Sample emblem photo', 'Sample office photo'])
        ->assertSee('id="leader-photo-2"', escape: false)
        ->assertSee('href="#leader-photo-1"', escape: false)
        ->assertSee(__('leaders.photo_close', [], 'en'));

    $first->delete();
    expect($leader->fresh()?->photos()->pluck('id')->all())->toBe([$second->id]);
});
