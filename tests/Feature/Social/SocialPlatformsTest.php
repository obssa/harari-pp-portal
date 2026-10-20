<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Social\Actions\AddPostByAddress;
use App\Domain\Social\Actions\FetchSocialSource;
use App\Domain\Social\Enums\Platform;
use App\Domain\Social\Models\SocialPost;
use App\Domain\Social\Models\SocialSource;
use App\Filament\Resources\SocialSources\Pages\ManageSocialSources;
use App\Settings\IntegrationSettings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestFiles;

use function Pest\Livewire\livewire;

const SAMPLE_CHANNEL = 'UCabcdefghijklmnopqrstuv';

function youtubeFeed(): string
{
    return '<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns:yt="http://www.youtube.com/xml/schemas/2015" xmlns:media="http://search.yahoo.com/mrss/" xmlns="http://www.w3.org/2005/Atom">
 <entry>
  <id>yt:video:abcdefghijk</id><yt:videoId>abcdefghijk</yt:videoId><title>Sample speech video</title>
  <published>2026-10-18T07:00:00+00:00</published>
  <media:group><media:description>Sample description</media:description><media:community><media:statistics views="1520"/></media:community></media:group>
 </entry>
 <entry><yt:videoId>bad id</yt:videoId><title>Broken</title><published>2026-10-18T07:00:00+00:00</published></entry>
</feed>';
}

function platformSource(Platform $platform, string $handle): SocialSource
{
    return SocialSource::query()->create(['platform' => $platform, 'handle' => $handle, 'name' => 'Sample '.$platform->value]);
}

it('reads a YouTube channel from its public feed', function () {
    $source = platformSource(Platform::YouTube, SAMPLE_CHANNEL);
    Http::fake([
        'www.youtube.com/feeds/*' => Http::response(youtubeFeed()),
        'i.ytimg.com/*' => Http::response((string) file_get_contents(TestFiles::image(480, 360)), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    expect(app(FetchSocialSource::class)->handle($source))->toBe(['added' => 1, 'updated' => 0, 'error' => null]);
    $post = SocialPost::query()->firstOrFail();
    expect($post->permalink)->toBe('https://www.youtube.com/watch?v=abcdefghijk')
        ->and($post->text)->toBe("Sample speech video\n\nSample description")
        ->and($post->metrics)->toBe(['views' => 1520])
        ->and($post->has_video)->toBeTrue()
        ->and($post->image_id)->not->toBeNull();
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'channel_id='.SAMPLE_CHANNEL));
});

it('finds a YouTube channel id from its name once, with the key', function () {
    $source = platformSource(Platform::YouTube, 'samplechannel');
    expect(app(FetchSocialSource::class)->handle($source)['error'])->toBe(__('social.error.youtube_channel_id'));

    $settings = app(IntegrationSettings::class);
    $settings->youtube_api_key = 'sample-key';
    $settings->save();
    Http::fake([
        'www.googleapis.com/*' => Http::response(['items' => [['id' => SAMPLE_CHANNEL]]]),
        'www.youtube.com/feeds/*' => Http::response(youtubeFeed()),
        'i.ytimg.com/*' => Http::response('', 404),
    ]);

    expect(app(FetchSocialSource::class)->handle($source->refresh())['added'])->toBe(1)
        ->and($source->refresh()->credentials['channel_id'] ?? null)->toBe(SAMPLE_CHANNEL);
});

it('reads a Facebook page with its token and explains when there is none', function () {
    $source = platformSource(Platform::Facebook, 'samplepage');
    expect(app(FetchSocialSource::class)->handle($source)['error'])->toBe(__('social.error.facebook_token'));

    $source->forceFill(['credentials' => ['token' => 'sample-page-token']])->save();
    Http::fake([
        'graph.facebook.com/*' => Http::response(['data' => [
            ['id' => '1_2', 'message' => 'Sample page post', 'created_time' => '2026-10-19T06:00:00+0000', 'permalink_url' => 'https://www.facebook.com/samplepage/posts/2', 'status_type' => 'added_video'],
            ['id' => 'no-link'],
        ]]),
    ]);

    expect(app(FetchSocialSource::class)->handle($source->refresh())['added'])->toBe(1);
    expect(SocialPost::query()->firstOrFail())->text->toBe('Sample page post')->has_video->toBeTrue();
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'samplepage/posts') && $request['access_token'] === 'sample-page-token');

    // The token is encrypted at rest.
    expect((string) DB::table('social_sources')->where('id', $source->id)->value('credentials'))->not->toContain('sample-page-token');
});

it('adds TikTok and X posts by their address with what the network tells', function () {
    $tiktok = platformSource(Platform::TikTok, 'sampleaccount');
    $x = platformSource(Platform::X, 'sampleaccount');
    Http::fake([
        'www.tiktok.com/oembed*' => Http::response(['title' => 'Sample TikTok video', 'thumbnail_url' => 'https://p16.tiktokcdn.com/sample.jpg']),
        'p16.tiktokcdn.com/*' => Http::response((string) file_get_contents(TestFiles::image(300, 400)), 200, ['Content-Type' => 'image/jpeg']),
        'publish.twitter.com/*' => Http::response(['html' => '<blockquote><p>Sample words on X</p>&mdash; Sample (@sampleaccount) <a href="https://x.com/x">October 19</a></blockquote>']),
    ]);

    $post = app(AddPostByAddress::class)->handle($tiktok, 'https://www.tiktok.com/@sampleaccount/video/7300000000000000000');
    expect($post->text)->toBe('Sample TikTok video')->and($post->image_id)->not->toBeNull()->and($post->has_video)->toBeTrue();
    expect(app(AddPostByAddress::class)->handle($x, 'https://x.com/sampleaccount/status/1')->text)->toBe('Sample words on X');

    expect(fn () => app(AddPostByAddress::class)->handle($tiktok, 'https://www.tiktok.com/@sampleaccount/video/7300000000000000000'))->toThrow(ValidationException::class)
        ->and(fn () => app(AddPostByAddress::class)->handle($tiktok, 'https://evil.example/video/1'))->toThrow(ValidationException::class);
});

it('lets managers save a Facebook page token and add posts from the accounts list', function () {
    $this->actingAs(staff(SystemRole::MediaManager));
    $page = platformSource(Platform::Facebook, 'samplepage');
    $instagram = platformSource(Platform::Instagram, 'sampleaccount');

    livewire(ManageSocialSources::class)
        ->callAction(TestAction::make('token')->table($page), data: ['token' => 'sample-token'])
        ->callAction(TestAction::make('addPost')->table($instagram), data: ['url' => 'https://www.instagram.com/p/sample/', 'text' => 'Sample caption'])
        ->assertHasNoActionErrors();

    expect($page->refresh()->credentials['token'] ?? null)->toBe('sample-token')
        ->and($instagram->posts()->firstOrFail()->text)->toBe('Sample caption');
});
