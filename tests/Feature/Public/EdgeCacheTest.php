<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Health\Checks\EdgeCacheCheck;
use App\Domain\Translation\Support\Locales;
use App\Filament\Pages\ManageIntegrations;
use App\Services\Cdn\CloudflareCache;
use App\Services\Cdn\PurgeEdgeCache;
use App\Settings\IntegrationSettings;
use App\Settings\SiteSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

function useCloudflare(): void
{
    $settings = app(IntegrationSettings::class);
    $settings->cloudflare_zone_id = str_repeat('a1', 16);
    $settings->cloudflare_api_token = 'sample-cloudflare-token';
    $settings->save();
}

it('asks Cloudflare to drop the cached pages once for a burst of public changes', function () {
    useCloudflare();
    Queue::fake();
    $publisher = staff(SystemRole::Publisher);

    app(SavePost::class)->handle(new Post, ['type' => 'news', 'translations' => ['en' => ['title' => 'Sample cached story', 'published' => true]]], $publisher);
    $site = app(SiteSettings::class);
    $site->contact_email = 'sample@example.org';
    $site->save();

    Queue::assertPushed(PurgeEdgeCache::class, 1);
});

it('asks for nothing without Cloudflare, or when only private records change', function () {
    Queue::fake();
    app(SavePost::class)->handle(new Post, ['type' => 'news', 'translations' => ['en' => ['title' => 'Sample uncached story']]], staff(SystemRole::Publisher));
    Queue::assertNotPushed(PurgeEdgeCache::class);

    useCloudflare();
    Queue::fake();
    staff(SystemRole::Editor)->forceFill(['name' => 'Sample renamed'])->save();
    Queue::assertNotPushed(PurgeEdgeCache::class);
});

it('purges only this site\'s host and records the outcome', function () {
    useCloudflare();
    $ok = true;
    Http::fake(['api.cloudflare.com/*' => function () use (&$ok) {
        return $ok
            ? Http::response(['success' => true, 'errors' => [], 'result' => ['id' => 'sample']])
            : Http::response(['success' => false, 'errors' => [['code' => 10000, 'message' => 'Authentication error']]], 403);
    }]);

    (new PurgeEdgeCache)->handle(app(CloudflareCache::class));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cloudflare.com/client/v4/zones/'.str_repeat('a1', 16).'/purge_cache'
        && $request['hosts'] === [parse_url((string) config('app.url'), PHP_URL_HOST)]
        && ! isset($request['purge_everything'])
        && $request->hasHeader('Authorization', 'Bearer sample-cloudflare-token'));
    expect(Cache::get(PurgeEdgeCache::LAST)['ok'])->toBeTrue()
        ->and(EdgeCacheCheck::new()->run()->status->value)->toBe('ok');

    $ok = false;
    (new PurgeEdgeCache)->handle(app(CloudflareCache::class));
    expect(Cache::get(PurgeEdgeCache::LAST))->toMatchArray(['ok' => false])
        ->and(Cache::get(PurgeEdgeCache::LAST)['error'])->toContain('Authentication error')
        ->and(EdgeCacheCheck::new()->run()->status->value)->toBe('warning');
});

it('keeps the Cloudflare token encrypted and checks the zone id', function () {
    $this->actingAs(staff(SystemRole::SuperAdmin));

    livewire(ManageIntegrations::class)
        ->fillForm(['cloudflare_zone_id' => 'not-a-zone'])
        ->call('save')
        ->assertHasFormErrors(['cloudflare_zone_id']);

    livewire(ManageIntegrations::class)
        ->fillForm(['cloudflare_zone_id' => str_repeat('b2', 16), 'cloudflare_api_token' => 'cf-sample-secret'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(DB::table('settings')->where('group', 'integrations')->where('name', 'cloudflare_api_token')->value('payload'))->not->toContain('cf-sample-secret')
        ->and(app(IntegrationSettings::class)->refresh()->cloudflare_api_token)->toBe('cf-sample-secret');
});

it('lets the edge cache keep HEAD answers too', function () {
    $response = $this->call('HEAD', '/en', cookies: [Locales::COOKIE => 'en']);

    expect($response->headers->get('Cache-Control'))->toContain('public', 's-maxage=');
});

it('never stores pages with a person\'s own details', function () {
    foreach (['/en/tickets', '/en/tickets/HPP-2222-2222', '/en/events/ticket/'.str_repeat('a', 40)] as $path) {
        expect($this->get($path)->headers->get('Cache-Control'))->toContain('no-store', 'private');
    }
});
