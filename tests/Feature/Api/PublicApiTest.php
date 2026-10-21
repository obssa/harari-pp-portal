<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Api\Actions\IssueApiKey;
use App\Domain\Api\Enums\ApiScope;
use App\Domain\Api\Models\ApiKey;
use App\Domain\Content\Actions\SavePage;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Domain\Documents\Actions\SaveDocument;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Models\Document;
use App\Domain\Events\Actions\SaveEvent;
use App\Domain\Events\Models\Event;
use App\Domain\Organisation\Actions\SaveLeader;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\Office;
use App\Domain\Projects\Actions\SaveProject;
use App\Domain\Projects\Models\Project;
use App\Http\Middleware\AuthenticateApiKey;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

/**
 * A key with these scopes; returns its text.
 *
 * @param  list<ApiScope>  $scopes
 * @param  array<string, mixed>  $data
 */
function apiKey(array $scopes = [], array $data = []): string
{
    $scopes = $scopes === [] ? ApiScope::cases() : $scopes;
    [, $plain] = app(IssueApiKey::class)->handle([
        'name' => 'Sample app (test)',
        'scopes' => array_map(fn (ApiScope $scope): string => $scope->value, $scopes),
        ...$data,
    ], staff(SystemRole::SuperAdmin));

    return $plain;
}

/**
 * @param  array<string, array<string, mixed>>  $translations
 * @param  list<string>  $publish
 */
function apiPost(array $translations, array $publish = [], string $type = 'news'): Post
{
    $publisher = staff(SystemRole::Publisher);
    $post = app(SavePost::class)->handle(new Post, ['type' => $type, 'translations' => $translations], $publisher);
    foreach ($publish as $locale) {
        app(ContentWorkflow::class)->apply($post, $locale, Transition::Publish, $publisher);
    }

    return $post->refresh();
}

/**
 * @param  array<string, mixed>  $data
 */
function apiRecord(string $action, object $model, array $data): mixed
{
    return app($action)->handle($model, $data, staff(SystemRole::Publisher));
}

beforeEach(function () {
    RateLimiter::clear('api-failed:127.0.0.1');
});

it('describes every endpoint in the OpenAPI document, readable without a key', function () {
    $document = $this->getJson('/api/v1/openapi.json')
        ->assertOk()
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonPath('servers.0.url', url('/api/v1'))
        ->assertJsonPath('components.securitySchemes.apiKey.name', AuthenticateApiKey::HEADER)
        ->json();

    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'api.v1.') && $route->getName() !== 'api.v1.openapi')
        ->map(fn ($route): string => '/'.preg_replace('#^api/v1/#', '', $route->uri()))
        ->values()->all();

    expect($routes)->not->toBeEmpty();
    foreach ($routes as $path) {
        expect($document['paths'])->toHaveKey($path);
    }
    expect(array_keys($document['paths']))->toHaveCount(count($routes));
});

it('refuses requests without a usable key and stops an address that keeps guessing', function () {
    $this->getJson('/api/v1/posts')
        ->assertUnauthorized()
        ->assertJsonPath('message', __('api.error.unauthenticated'))
        ->assertHeader('WWW-Authenticate');
    $this->getJson('/api/v1/posts', ['X-Api-Key' => 'hpp_wrong'])->assertUnauthorized();

    $revoked = apiKey();
    app(IssueApiKey::class)->revoke(ApiKey::query()->latest('id')->firstOrFail());
    $this->getJson('/api/v1/posts', ['X-Api-Key' => $revoked])->assertUnauthorized();

    $expired = apiKey(data: ['expires_at' => now()->addHour()->toDateTimeString()]);
    $this->travel(2)->hours();
    $this->getJson('/api/v1/posts', ['X-Api-Key' => $expired])->assertUnauthorized();
    $this->travelBack();

    RateLimiter::clear('api-failed:127.0.0.1');
    for ($i = 0; $i < AuthenticateApiKey::FAILURES_PER_MINUTE; $i++) {
        $this->getJson('/api/v1/posts', ['X-Api-Key' => 'hpp_guess'.$i]);
    }
    $this->getJson('/api/v1/posts', ['X-Api-Key' => apiKey()])
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

it('keeps each key to its scopes and its rate, and notes when it was used', function () {
    $key = apiKey([ApiScope::Events], ['rate_per_minute' => 2]);

    $this->getJson('/api/v1/posts', ['X-Api-Key' => $key])
        ->assertForbidden()
        ->assertJsonPath('message', __('api.error.scope'));

    $this->getJson('/api/v1/events', ['X-Api-Key' => $key])
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '2')
        ->assertHeader('X-RateLimit-Remaining', '1');
    $this->getJson('/api/v1/events', ['X-Api-Key' => $key])->assertOk();
    $this->getJson('/api/v1/events', ['X-Api-Key' => $key])
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('message', __('api.error.too_many'));

    expect(ApiKey::query()->latest('id')->firstOrFail()->last_used_at)->not->toBeNull();
});

it('answers without a session or cookies, with an ETag apps can send back', function () {
    $key = apiKey();
    apiPost(['en' => ['title' => 'Sample cached news']], ['en']);

    $first = $this->getJson('/api/v1/posts', ['X-Api-Key' => $key])
        ->assertOk()
        ->assertHeader('Content-Language', 'en');
    expect($first->headers->getCookies())->toBeEmpty()
        ->and((string) $first->headers->get('Cache-Control'))->toContain('private')
        ->and((string) $first->headers->get('Vary'))->toContain('X-Api-Key');

    $etag = (string) $first->headers->get('ETag');
    expect($etag)->not->toBe('');

    $this->get('/api/v1/posts', ['X-Api-Key' => $key, 'If-None-Match' => $etag, 'Accept' => 'application/json'])
        ->assertStatus(304)
        ->assertContent('');
});

it('lists live news in the language asked for, with fallback, filters and pages', function () {
    $key = apiKey([ApiScope::Content]);
    apiPost(['en' => ['title' => 'Sample live news', 'excerpt' => 'Sample summary']], ['en']);
    apiPost(['en' => ['title' => 'Sample draft news']]);
    apiPost(['en' => ['title' => 'Sample statement']], ['en'], 'statement');
    apiPost(['am' => ['title' => 'የናሙና ዜና']], ['am']);

    $this->getJson('/api/v1/posts?lang=en', ['X-Api-Key' => $key])
        ->assertOk()
        ->assertJsonPath('meta.lang', 'en')
        ->assertJsonPath('meta.total', 2)
        ->assertJsonFragment(['title' => 'Sample live news', 'excerpt' => 'Sample summary', 'lang' => 'en'])
        ->assertJsonMissing(['title' => 'Sample draft news'])
        ->assertJsonMissing(['title' => 'የናሙና ዜና']);

    $this->getJson('/api/v1/posts?lang=en&type=statement', ['X-Api-Key' => $key])
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.title', 'Sample statement')
        ->assertJsonPath('data.0.type', 'statement');

    // Harari readers get Amharic, marked as such.
    $this->getJson('/api/v1/posts?lang=har', ['X-Api-Key' => $key])
        ->assertJsonPath('meta.lang', 'har')
        ->assertJsonPath('data.0.title', 'የናሙና ዜና')
        ->assertJsonPath('data.0.lang', 'am');

    $this->getJson('/api/v1/posts?lang=en&per_page=1&page=2', ['X-Api-Key' => $key])
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('meta.page', 2)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonCount(1, 'data');

    $this->getJson('/api/v1/posts?per_page=500', ['X-Api-Key' => $key])
        ->assertJsonPath('meta.per_page', config('portal.api.max_per_page'));

    $this->getJson('/api/v1/posts?lang=fr', ['X-Api-Key' => $key])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('api.error.language', ['languages' => 'en, am, om, har']));
});

it('shows one post by slug or id with its text and languages', function () {
    $key = apiKey([ApiScope::Content]);
    $post = apiPost([
        'en' => ['title' => 'Sample council session', 'body' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Sample paragraph.']]]]]],
        'am' => ['title' => 'የናሙና ጉባኤ'],
    ], ['en', 'am']);
    $slug = (string) $post->translation('en')?->slug;

    $this->getJson("/api/v1/posts/{$slug}", ['X-Api-Key' => $key])
        ->assertOk()
        ->assertJsonPath('data.id', $post->id)
        ->assertJsonPath('data.title', 'Sample council session')
        ->assertJsonPath('data.url', route('news.show', ['locale' => 'en', 'slug' => $slug]))
        ->assertJsonPath('data.alternates.am', route('news.show', ['locale' => 'am', 'slug' => (string) $post->translation('am')?->slug]))
        ->assertJson(fn ($json) => $json->where('data.body_html', fn (string $html): bool => str_contains($html, 'Sample paragraph.'))->etc());

    $this->getJson("/api/v1/posts/{$post->id}?lang=am", ['X-Api-Key' => $key])
        ->assertOk()
        ->assertJsonPath('data.title', 'የናሙና ጉባኤ')
        ->assertJsonPath('data.lang', 'am');

    $draft = apiPost(['en' => ['title' => 'Sample unpublished']]);
    $this->getJson('/api/v1/posts/'.$draft->id, ['X-Api-Key' => $key])
        ->assertNotFound()
        ->assertJsonPath('message', __('api.error.not_found'));
    $this->getJson('/api/v1/posts/no-such-post', ['X-Api-Key' => $key])->assertNotFound();
});

it('lists and shows pages with their address', function () {
    $key = apiKey([ApiScope::Content]);
    $about = apiRecord(SavePage::class, new Page, ['translations' => ['en' => ['title' => 'Sample about', 'published' => true]]]);
    apiRecord(SavePage::class, new Page, ['parent_id' => $about->id, 'translations' => ['en' => ['title' => 'Sample history', 'published' => true]]]);

    $this->getJson('/api/v1/pages', ['X-Api-Key' => $key])
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonFragment(['title' => 'Sample history', 'path' => 'sample-about/sample-history']);

    $this->getJson('/api/v1/pages/sample-history', ['X-Api-Key' => $key])
        ->assertOk()
        ->assertJsonPath('data.parent_id', $about->id)
        ->assertJsonPath('data.url', route('page.show', ['locale' => 'en', 'path' => 'sample-about/sample-history']));
});

it('lists upcoming, past and dated events and shows one with its registration', function () {
    $key = apiKey([ApiScope::Events]);
    $soon = apiRecord(SaveEvent::class, new Event, [
        'starts_at' => CarbonImmutable::parse('+3 days 09:00')->toDateTimeString(),
        'registration_enabled' => true, 'capacity' => 50,
        'translations' => ['en' => ['title' => 'Sample town hall', 'venue' => 'Sample hall', 'published' => true]],
    ]);
    apiRecord(SaveEvent::class, new Event, [
        'starts_at' => CarbonImmutable::parse('-10 days 09:00')->toDateTimeString(),
        'translations' => ['en' => ['title' => 'Sample past meeting', 'published' => true]],
    ]);

    $this->getJson('/api/v1/events', ['X-Api-Key' => $key])
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.title', 'Sample town hall')
        ->assertJsonPath('data.0.venue', 'Sample hall');

    $this->getJson('/api/v1/events?when=past', ['X-Api-Key' => $key])
        ->assertJsonPath('data.0.title', 'Sample past meeting');

    $day = CarbonImmutable::parse('-10 days')->toDateString();
    $this->getJson("/api/v1/events?from={$day}&to={$day}", ['X-Api-Key' => $key])
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.title', 'Sample past meeting');

    $this->getJson('/api/v1/events/'.$soon->id, ['X-Api-Key' => $key])
        ->assertOk()
        ->assertJsonPath('data.registration.state', Event::REGISTRATION_OPEN)
        ->assertJsonPath('data.registration.places_left', 50)
        ->assertJsonPath('data.calendar_url', route('events.ics', ['locale' => 'en', 'slug' => 'sample-town-hall']));
});

it('gives public documents only, never members-only ones', function () {
    $key = apiKey([ApiScope::Documents]);
    apiRecord(SaveDocument::class, new Document, ['type' => DocumentType::Report->value, 'year' => 2025, 'translations' => ['en' => ['title' => 'Sample annual report', 'published' => true]]]);
    $private = apiRecord(SaveDocument::class, new Document, ['type' => DocumentType::Report->value, 'access' => Document::ACCESS_MEMBERS, 'translations' => ['en' => ['title' => 'Sample members report', 'published' => true]]]);
    apiRecord(SaveDocument::class, new Document, [
        'type' => DocumentType::Vacancy->value, 'deadline_at' => now()->addWeek()->toDateTimeString(),
        'translations' => ['en' => ['title' => 'Sample vacancy', 'published' => true]],
    ]);

    $this->getJson('/api/v1/documents', ['X-Api-Key' => $key])
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonMissing(['title' => 'Sample members report']);

    $this->getJson('/api/v1/documents?year=2025', ['X-Api-Key' => $key])->assertJsonPath('data.0.title', 'Sample annual report');
    $this->getJson('/api/v1/documents?open=1', ['X-Api-Key' => $key])
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.title', 'Sample vacancy')
        ->assertJsonPath('data.0.closed', false);

    $this->getJson('/api/v1/documents/'.$private->id, ['X-Api-Key' => $key])->assertNotFound();
});

it('lists offices and leaders and gives a leader page the public contacts', function () {
    $key = apiKey([ApiScope::Directory]);
    $office = apiRecord(SaveOffice::class, new Office, ['type' => 'woreda', 'phone' => '+251900000000', 'translations' => ['en' => ['name' => 'Sample woreda office', 'published' => true]]]);
    $leader = apiRecord(SaveLeader::class, new Leader, [
        'body' => 'executive_committee', 'office_id' => $office->id, 'email' => 'sample.leader@example.org',
        'translations' => ['en' => ['name' => 'Sample Chair', 'position' => 'Sample position', 'published' => true]],
    ]);

    $this->getJson('/api/v1/offices?type=woreda', ['X-Api-Key' => $key])
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Sample woreda office')
        ->assertJsonPath('data.0.phone', '+251900000000');

    $this->getJson('/api/v1/leaders?office='.$office->id, ['X-Api-Key' => $key])
        ->assertJsonPath('data.0.name', 'Sample Chair')
        ->assertJsonMissingPath('data.0.email');

    $this->getJson('/api/v1/leaders/'.$leader->id, ['X-Api-Key' => $key])
        ->assertJsonPath('data.position', 'Sample position')
        ->assertJsonPath('data.email', 'sample.leader@example.org');

    $this->getJson('/api/v1/offices/sample-woreda-office', ['X-Api-Key' => $key])->assertJsonPath('data.id', $office->id);
});

it('shows a project budget only when it is public', function () {
    $key = apiKey([ApiScope::Projects]);
    apiRecord(SaveProject::class, new Project, ['status' => 'ongoing', 'budget' => '1500000', 'translations' => ['en' => ['title' => 'Sample water supply', 'published' => true]]]);
    apiRecord(SaveProject::class, new Project, ['status' => 'completed', 'budget' => '900000', 'budget_public' => true, 'translations' => ['en' => ['title' => 'Sample school', 'published' => true]]]);

    $this->getJson('/api/v1/projects?status=ongoing', ['X-Api-Key' => $key])
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.budget', null);
    $this->getJson('/api/v1/projects?status=completed', ['X-Api-Key' => $key])
        ->assertJsonPath('data.0.title', 'Sample school')
        ->assertJsonPath('data.0.budget', 900000);
});

it('searches the site like the search page', function () {
    $key = apiKey([ApiScope::Search]);
    apiPost(['en' => ['title' => 'Sample irrigation news']], ['en']);

    $this->getJson('/api/v1/search?q=irrigation', ['X-Api-Key' => $key])
        ->assertOk()
        ->assertJsonPath('meta.searched', true)
        ->assertJsonPath('data.0.kind', 'news')
        ->assertJsonPath('data.0.title', 'Sample irrigation news');

    $this->getJson('/api/v1/search?q=irrigation&kind=planets', ['X-Api-Key' => $key])->assertUnprocessable();
    $this->getJson('/api/v1/search?q=', ['X-Api-Key' => $key])
        ->assertOk()
        ->assertJsonPath('meta.searched', false)
        ->assertJsonCount(0, 'data');
});
