<?php

use App\Domain\Translation\Support\Locales;
use App\Http\Middleware\CachePublicPages;

it('serves public pages without a session, so they can be shared by the edge cache', function () {
    $response = $this->withUnencryptedCookie(Locales::COOKIE, 'am')->get('/am')->assertOk();

    expect($response->headers->getCookies())->toBe([])
        ->and($response->headers->get('Cache-Control'))
        ->toContain('public', 's-maxage='.CachePublicPages::EDGE_SECONDS, 'max-age=0');
});

it('keeps the first visit in a new language private, because it sets the language cookie', function () {
    $response = $this->get('/om')->assertOk();

    $response->assertPlainCookie(Locales::COOKIE, 'om');
    expect($response->headers->get('Cache-Control'))->toContain('private')->not->toContain('s-maxage');
});

it('never shares the language redirect', function () {
    expect($this->get('/')->headers->get('Cache-Control'))->toContain('private', 'no-store');
});

it('still gives the admin panel its session', function () {
    $response = $this->get('/admin/login')->assertOk();

    expect(collect($response->headers->getCookies())->map->getName()->all())
        ->toContain(config('session.cookie'));
});
