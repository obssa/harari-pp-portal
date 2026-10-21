<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Domain\Social\Actions\SharePost;
use App\Domain\Social\Enums\Platform;
use App\Domain\Social\Models\SocialShare;
use App\Domain\Social\Models\SocialSource;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\RelationManagers\SocialSharesRelationManager;
use App\Settings\IntegrationSettings;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Livewire\livewire;

/**
 * @param  list<string>  $publish
 */
function shareablePost(array $publish = ['en', 'am']): Post
{
    $publisher = staff(SystemRole::Publisher);
    $post = app(SavePost::class)->handle(new Post, ['type' => 'news', 'translations' => [
        'en' => ['title' => 'Sample council session', 'excerpt' => 'Sample summary of the session.'],
        'am' => ['title' => 'የናሙና የምክር ቤት ጉባኤ', 'excerpt' => 'የናሙና ማጠቃለያ።'],
        'om' => ['title' => 'Walgahii mana maree fakkeenyaa'],
    ]], $publisher);
    foreach ($publish as $locale) {
        app(ContentWorkflow::class)->apply($post, $locale, Transition::Publish, $publisher);
    }

    return $post->refresh();
}

/**
 * The branch office's page and channel as social sources.
 *
 * @return array{0: SocialSource, 1: SocialSource}
 */
function shareAccounts(): array
{
    $settings = app(IntegrationSettings::class);
    $settings->telegram_bot_token = 'sample-bot-token';
    $settings->save();

    return [
        SocialSource::query()->create(['platform' => Platform::Facebook, 'handle' => '1234567890', 'name' => 'Sample branch page', 'credentials' => ['token' => 'sample-page-token']]),
        SocialSource::query()->create(['platform' => Platform::Telegram, 'handle' => 'samplechannel', 'name' => 'Sample branch channel']),
    ];
}

it('shares a published post to the Facebook page and the Telegram channel with its link', function () {
    [$page, $channel] = shareAccounts();
    $post = shareablePost();
    Http::fake([
        'graph.facebook.com/*' => Http::response(['id' => '1234567890_555']),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]]),
    ]);

    $count = app(SharePost::class)->share($post, [$page->id, $channel->id], 'am', null, staff(SystemRole::Publisher));

    $link = route('news.show', ['locale' => 'am', 'slug' => (string) $post->translation('am')?->slug]);
    expect($count)->toBe(2);
    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), '/1234567890/feed')
        && $request['link'] === $link && $request['access_token'] === 'sample-page-token'
        && str_starts_with((string) $request['message'], 'የናሙና የምክር ቤት ጉባኤ'));
    Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'botsample-bot-token/sendMessage')
        && $request['chat_id'] === '@samplechannel' && str_ends_with((string) $request['text'], $link));

    $shares = SocialShare::query()->orderBy('id')->get();
    expect($shares->pluck('status')->all())->toBe([SocialShare::SENT, SocialShare::SENT])
        ->and($shares[0]->external_url)->toBe('https://www.facebook.com/1234567890_555')
        ->and($shares[1]->external_url)->toBe('https://t.me/samplechannel/77')
        ->and($shares[0]->locale)->toBe('am');
});

it('keeps each account\'s outcome, so a refused page does not stop the channel', function () {
    [$page, $channel] = shareAccounts();
    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Sample: the token cannot publish']], 403),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 78]]),
    ]);

    app(SharePost::class)->share(shareablePost(), [$page->id, $channel->id], 'en', 'Sample own words', staff(SystemRole::Publisher));

    $failed = SocialShare::query()->where('platform', 'facebook')->sole();
    expect($failed->status)->toBe(SocialShare::FAILED)
        ->and($failed->error)->toContain('the token cannot publish')
        ->and($failed->message)->toBe('Sample own words')
        ->and(SocialShare::query()->where('platform', 'telegram')->sole()->status)->toBe(SocialShare::SENT);
});

it('shares only live languages, to chosen accounts, by staff allowed to share', function () {
    [$page] = shareAccounts();
    $post = shareablePost(['en']);
    Http::fake();

    expect(fn () => app(SharePost::class)->share($post, [$page->id], 'om', null, staff(SystemRole::Publisher)))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(SharePost::class)->share($post, [], 'en', null, staff(SystemRole::Publisher)))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(SharePost::class)->share($post, [$page->id], 'en', null, staff(SystemRole::Editor)))
        ->toThrow(HttpException::class);
    expect(SharePost::languages($post))->toBe(['en'])
        ->and(SocialShare::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('offers "Share" on the post page and lists the shares there', function () {
    [$page, $channel] = shareAccounts();
    $post = shareablePost();
    Http::fake([
        'graph.facebook.com/*' => Http::response(['id' => '1234567890_556']),
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 79]]),
    ]);
    $this->actingAs(staff(SystemRole::Publisher));

    livewire(EditPost::class, ['record' => $post->getRouteKey()])
        ->assertActionVisible('share')
        ->callAction('share', data: ['accounts' => [$channel->id], 'language' => 'en', 'message' => ''])
        ->assertHasNoActionErrors()
        ->assertNotified(__('social.share.queued', ['count' => 1]));

    livewire(SocialSharesRelationManager::class, ['ownerRecord' => $post, 'pageClass' => EditPost::class])
        ->assertOk()
        ->assertSee('Sample branch channel')
        ->assertSee(__('social.share.status.sent'));

    $this->actingAs(staff(SystemRole::Editor));
    livewire(EditPost::class, ['record' => $post->getRouteKey()])->assertActionHidden('share');
});
