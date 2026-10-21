<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Api\Actions\IssueApiKey;
use App\Domain\Sites\Models\Site;
use App\Support\SlowQueries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
| ADR-0061: one application instance serving request after request, as
| Octane does. Between requests Octane forgets the scoped instances; the
| test does the same.
*/

function nextRequest(): void
{
    app()->forgetScopedInstances();
}

it('serves each request in its own language, whatever came before', function () {
    $this->get('/am/news')->assertOk()->assertSee('<html lang="am">', escape: false);
    nextRequest();
    $this->get('/en/news')->assertOk()->assertSee('<html lang="en">', escape: false)
        ->assertSee('<link rel="canonical" href="'.url('/en/news').'">', escape: false);

    [, $key] = app(IssueApiKey::class)->handle(['name' => 'Sample worker app (test)', 'scopes' => ['content']], staff(SystemRole::SuperAdmin));
    nextRequest();
    $this->getJson('/api/v1/posts?lang=om', ['X-Api-Key' => $key])->assertOk()->assertHeader('Content-Language', 'om');
    nextRequest();
    // The API's language does not stay for the next page, nor in the addresses it builds.
    $this->get('/en')->assertOk()->assertSee('<html lang="en">', escape: false)
        ->assertSee('<link rel="canonical" href="'.url('/en').'">', escape: false);
});

it('finds the site of each request by its host, not the first request\'s', function () {
    $default = Site::query()->where('is_default', true)->firstOrFail();
    $other = Site::query()->create(['key' => 'sample-other', 'name' => 'Sample other site', 'domain' => 'other.example.test', 'is_default' => false]);

    $this->get('http://other.example.test/up')->assertOk();
    expect(Site::current()->id)->toBe($other->id);

    nextRequest();
    $this->get('http://localhost/up')->assertOk();
    expect(Site::current()->id)->toBe($default->id);
});

it('logs a request that spends too long in the database', function () {
    Log::spy();
    SlowQueries::watch(1);

    DB::select('select sleep(0.01)');

    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'Slow database work'
        && $context['total_ms'] >= 1 && str_contains($context['query'], 'sleep'))->once();
});
