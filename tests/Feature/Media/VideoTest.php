<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Models\Category;
use App\Domain\Media\Actions\SaveVideo;
use App\Domain\Media\Models\Video;
use App\Domain\Media\Support\VideoLink;
use App\Filament\Resources\Videos\Pages\CreateVideo;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

function videoThumbnailJpeg(): string
{
    $image = imagecreatetruecolor(480, 360);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 40, 40));
    ob_start();
    imagejpeg($image, null, 85);

    return (string) ob_get_clean();
}

/**
 * @param  array<string, array<string, mixed>>  $translations
 * @param  array<string, mixed>  $data
 */
function makeVideo(array $translations, string $url = 'https://www.youtube.com/watch?v=abcdefghijk', array $data = []): Video
{
    Http::fake(['i.ytimg.com/*' => Http::response(videoThumbnailJpeg(), 200, ['Content-Type' => 'image/jpeg'])]);

    return app(SaveVideo::class)->handle(new Video, ['url' => $url, 'translations' => $translations, ...$data], staff(SystemRole::Publisher));
}

it('understands YouTube, Facebook and Telegram addresses', function (string $url, ?string $provider, ?string $youtube) {
    $link = VideoLink::parse($url);

    expect($link?->provider)->toBe($provider)->and($link?->youtubeId)->toBe($youtube);
})->with([
    ['https://www.youtube.com/watch?v=abcdefghijk&t=10', 'youtube', 'abcdefghijk'],
    ['https://youtu.be/abcdefghijk', 'youtube', 'abcdefghijk'],
    ['https://www.youtube.com/live/abcdefghijk?si=x', 'youtube', 'abcdefghijk'],
    ['https://m.facebook.com/watch/?v=123', 'facebook', null],
    ['https://t.me/hrprosperityparty/23917', 'telegram', null],
    ['http://youtu.be/abcdefghijk', null, null],
    ['https://example.org/video', null, null],
    ['https://t.me/hrprosperityparty', null, null],
]);

it('saves videos, copying the YouTube picture into the library once', function () {
    $video = makeVideo(['en' => ['title' => 'Sample speech video', 'published' => true]], 'https://youtu.be/abcdefghijk');

    expect($video->provider)->toBe('youtube')
        ->and($video->url)->toBe('https://www.youtube.com/watch?v=abcdefghijk')
        ->and($video->thumbnail?->credit)->toBe('YouTube')
        ->and($video->thumbnail?->isUsed())->toBeTrue();

    $again = app(SaveVideo::class)->handle($video, ['url' => $video->url, 'translations' => ['en' => ['title' => 'Sample speech video', 'published' => true]]], staff(SystemRole::Publisher));
    expect($again->thumbnail_id)->toBe($video->thumbnail_id);
    Http::assertSentCount(1);

    $facebook = makeVideo(['en' => ['title' => 'Sample Facebook video']], 'https://www.facebook.com/watch/?v=987');
    expect($facebook->provider)->toBe('facebook')->and($facebook->thumbnail_id)->toBeNull();
});

it('refuses unknown addresses and live broadcasts without a sensible time', function (array $data) {
    expect(fn () => makeVideo(['en' => ['title' => 'Sample']], ...$data))->toThrow(ValidationException::class);
})->with([
    'unknown address' => [['https://example.org/clip']],
    'live without start' => [['https://youtu.be/abcdefghijk', ['is_live' => true]]],
    'end before start' => [['https://youtu.be/abcdefghijk', ['is_live' => true, 'live_starts_at' => '2026-10-20 10:00', 'live_ends_at' => '2026-10-20 09:00']]],
]);

it('lists published videos by playlist and plays YouTube with the privacy-enhanced player', function () {
    $playlist = Category::query()->create(['type' => Category::TYPE_VIDEO]);
    $playlist->putTranslation('en', ['name' => 'Sample speeches']);
    makeVideo(['en' => ['title' => 'Sample listed speech', 'summary' => 'Sample description', 'published' => true]], data: ['category_id' => $playlist->id, 'recorded_on' => '2026-10-10']);
    makeVideo(['en' => ['title' => 'Sample other video', 'published' => true]], 'https://youtu.be/bcdefghijkl');
    makeVideo(['en' => ['title' => 'Sample draft video']]);

    $this->get('/en/videos')->assertOk()->assertSee('Sample listed speech')->assertSee('Sample speeches')->assertDontSee('Sample draft video')->assertDontSee('i.ytimg.com');
    $this->get('/en/videos?playlist=sample-speeches')->assertSee('Sample listed speech')->assertDontSee('Sample other video');

    $html = (string) $this->get('/en/videos/sample-listed-speech')
        ->assertOk()
        ->assertSee('https://www.youtube-nocookie.com/embed/abcdefghijk?rel=0', escape: false)
        ->assertSee('Sample description')
        ->getContent();
    expect($html)->toContain('"@type":"VideoObject"');
});

it('links Facebook and Telegram videos instead of embedding them', function () {
    makeVideo(['en' => ['title' => 'Sample Telegram clip', 'published' => true]], 'https://t.me/hrprosperityparty/23917');

    $this->get('/en/videos/sample-telegram-clip')
        ->assertOk()
        ->assertSee('href="https://t.me/hrprosperityparty/23917"', escape: false)
        ->assertSee(__('video.watch_on', ['provider' => __('video.provider.telegram', [], 'en')], 'en'))
        ->assertDontSee('<iframe', escape: false);
});

it('shows the live broadcast now, the next one, or says there is none', function () {
    $this->get('/en/live')->assertOk()->assertSee(__('video.no_live', [], 'en'));

    makeVideo(['en' => ['title' => 'Sample council live', 'published' => true]], data: ['is_live' => true, 'live_starts_at' => now()->subHour()->toDateTimeString()]);
    makeVideo(['en' => ['title' => 'Sample tomorrow live', 'published' => true]], 'https://youtu.be/bcdefghijkl', ['is_live' => true, 'live_starts_at' => now()->addDay()->toDateTimeString()]);
    makeVideo(['en' => ['title' => 'Sample ended live', 'published' => true]], 'https://youtu.be/cdefghijklm', ['is_live' => true, 'live_starts_at' => now()->subHours(3)->toDateTimeString(), 'live_ends_at' => now()->subHours(2)->toDateTimeString()]);

    $this->get('/en/live')
        ->assertOk()
        ->assertSee('Sample council live')
        ->assertSee('youtube-nocookie.com/embed/abcdefghijk', escape: false)
        ->assertSee('Sample tomorrow live')
        ->assertSee(__('video.next_live', [], 'en'));
    $this->get('/en/videos')->assertSee(__('video.live_now_link', [], 'en'));
    expect(Video::query()->liveNow()->count())->toBe(1);
});

it('puts the video of the week on the homepage and the videos in the sitemaps', function () {
    makeVideo(['en' => ['title' => 'Sample newest video', 'published' => true]], 'https://youtu.be/bcdefghijkl');
    makeVideo(['en' => ['title' => 'Sample featured video', 'published' => true]], data: ['is_featured' => true]);

    $this->get('/en')->assertSee('id="home-video"', escape: false)->assertSee('Sample featured video')->assertDontSee('Sample newest video');
    $this->get('/sitemaps/en.xml')->assertSee(url('/en/videos/sample-featured-video'), escape: false);
});

it('lets those who manage videos add them in the admin', function () {
    $this->actingAs(staff(SystemRole::Author))->get('/admin/videos')->assertForbidden();

    $this->actingAs(staff(SystemRole::MediaManager));
    Http::fake(['i.ytimg.com/*' => Http::response(videoThumbnailJpeg(), 200, ['Content-Type' => 'image/jpeg'])]);

    livewire(CreateVideo::class)
        ->fillForm([
            'url' => 'https://www.youtube.com/watch?v=abcdefghijk',
            'translations' => ['am' => ['title' => 'የናሙና ቪዲዮ', 'published' => true]],
            'is_live' => true,
            'live_starts_at' => '2026-11-01 18:00:00',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $video = Video::query()->firstOrFail();
    expect($video->translation('am', fallback: false)?->isLive())->toBeTrue()
        // Entered in Addis Ababa time, stored in UTC.
        ->and($video->live_starts_at?->utc()->format('Y-m-d H:i'))->toBe('2026-11-01 15:00');
});
