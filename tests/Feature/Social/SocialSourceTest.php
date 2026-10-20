<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Social\Actions\FetchSocialSource;
use App\Domain\Social\Enums\Platform;
use App\Domain\Social\Models\SocialPost;
use App\Domain\Social\Models\SocialSource;
use App\Filament\Resources\SocialPosts\Pages\ListSocialPosts;
use App\Filament\Resources\SocialSources\Pages\ManageSocialSources;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestFiles;

use function Pest\Livewire\livewire;

/**
 * A channel's public preview page with the given messages.
 *
 * @param  list<array{id: int, at: string, text?: string, photos?: list<string>, video?: bool}>  $messages
 */
function socialChannelHtml(string $channel, array $messages): string
{
    $html = '<html><body><section class="tgme_channel_history">';
    foreach ($messages as $m) {
        $html .= '<div class="tgme_widget_message_wrap js-widget_message_wrap"><div class="tgme_widget_message js-widget_message" data-post="'.$channel.'/'.$m['id'].'"><div class="tgme_widget_message_bubble">';
        foreach ($m['photos'] ?? [] as $url) {
            $html .= '<a class="tgme_widget_message_photo_wrap" href="https://t.me/x" style="width:100px;background-image:url(\''.$url.'\')"></a>';
        }
        if ($m['video'] ?? false) {
            $html .= '<a class="tgme_widget_message_video_player" href="#"><i class="tgme_widget_message_video_thumb"></i></a>';
        }
        if (isset($m['text'])) {
            $html .= '<div class="tgme_widget_message_text js-message_text" dir="auto">'.e($m['text']).'</div>';
        }
        $html .= '<div class="tgme_widget_message_footer"><a class="tgme_widget_message_date" href="#"><time datetime="'.$m['at'].'" class="time">10:00</time></a></div></div></div></div>';
    }

    return $html.'</section></body></html>';
}

function telegramSource(string $handle = 'samplechannel'): SocialSource
{
    return SocialSource::query()->create(['platform' => Platform::Telegram, 'handle' => $handle, 'name' => 'Sample channel']);
}

it('reads a Telegram channel, copying the first picture and keeping staff decisions', function () {
    $source = telegramSource();
    Http::fake([
        't.me/s/samplechannel*' => Http::response(socialChannelHtml('samplechannel', [
            ['id' => 10, 'at' => '2026-10-18T08:00:00+00:00', 'text' => 'Sample older post'],
            ['id' => 11, 'at' => '2026-10-19T08:00:00+00:00', 'text' => 'Sample newer post', 'photos' => ['https://cdn4.telesco.pe/file/sample-photo.jpg']],
            ['id' => 12, 'at' => '2026-10-19T09:00:00+00:00'],
        ])),
        'cdn4.telesco.pe/*' => Http::response((string) file_get_contents(TestFiles::image(320, 200)), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $result = app(FetchSocialSource::class)->handle($source);

    expect($result)->toBe(['added' => 2, 'updated' => 0, 'error' => null])
        ->and($source->refresh()->last_fetched_at)->not->toBeNull();
    $newer = SocialPost::query()->where('external_id', '11')->firstOrFail();
    expect($newer->permalink)->toBe('https://t.me/samplechannel/11')
        ->and($newer->image?->credit)->toBe('Sample channel')
        ->and($newer->posted_at->toIso8601String())->toBe('2026-10-19T08:00:00+00:00');

    $newer->update(['is_hidden' => true]);
    $again = app(FetchSocialSource::class)->handle($source);
    expect($again['added'])->toBe(0)->and($again['updated'])->toBe(2)
        ->and($newer->refresh()->is_hidden)->toBeTrue()
        ->and(SocialPost::query()->visible()->count())->toBe(1);
});

it('keeps the error of a failing source and reads only sources that are due', function () {
    $failing = telegramSource('brokenchannel');
    $recent = telegramSource('recentchannel');
    $recent->forceFill(['last_fetched_at' => CarbonImmutable::now()->subMinutes(10)])->save();
    $notYet = SocialSource::query()->create(['platform' => Platform::Facebook, 'handle' => 'samplepage', 'name' => 'Sample page']);
    Http::fake(['t.me/*' => Http::response('', 500)]);

    $this->artisan('social:fetch')->assertSuccessful();

    expect($failing->refresh()->last_error)->not->toBeNull()
        ->and($recent->refresh()->last_fetched_at?->diffInMinutes(CarbonImmutable::now()) >= 9)->toBeTrue()
        ->and($notYet->refresh()->last_error)->toContain(Platform::Facebook->label());
    expect(SocialSource::query()->due()->pluck('handle')->all())->toBe([]);
});

it('lets those who manage social media add accounts and moderate posts, and keeps others out', function () {
    $this->actingAs(staff(SystemRole::Author))->get('/admin/social-sources')->assertForbidden();
    $this->actingAs(staff(SystemRole::Author))->get('/admin/social-posts')->assertForbidden();

    $this->actingAs(staff(SystemRole::MediaManager));
    livewire(ManageSocialSources::class)
        ->callAction('create', data: ['platform' => 'telegram', 'handle' => 'not valid!', 'name' => 'Sample'])
        ->assertHasActionErrors(['handle']);
    livewire(ManageSocialSources::class)
        ->callAction('create', data: ['platform' => 'telegram', 'handle' => 'hrprosperityparty', 'name' => 'Sample branch', 'interval_minutes' => 30, 'enabled' => true])
        ->assertHasNoActionErrors();
    $source = SocialSource::query()->firstOrFail();
    expect($source->url())->toBe('https://t.me/hrprosperityparty');

    $post = $source->posts()->create(['external_id' => '1', 'posted_at' => now(), 'text' => 'Sample post', 'permalink' => 'https://t.me/hrprosperityparty/1']);
    livewire(ListSocialPosts::class)
        ->assertCanSeeTableRecords([$post])
        ->callAction(TestAction::make('hide')->table($post))
        ->callAction(TestAction::make('pin')->table($post));
    expect($post->refresh()->is_hidden)->toBeTrue()->and($post->is_pinned)->toBeTrue();
});
