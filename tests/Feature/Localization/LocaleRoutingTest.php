<?php

use App\Domain\Translation\Support\Locales;

it('sends "/" to the default language when nothing else is known', function () {
    // Test requests carry a default Accept-Language; send an empty one.
    $response = $this->withHeader('Accept-Language', '')->get('/');

    $response->assertRedirect('/am')->assertHeader('Vary', 'Accept-Language, Cookie');
    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Cache-Control'))->toContain('private');
});

it('falls back to the configured default when the admin setting is unavailable', function () {
    Locales::resolveDefaultUsing(fn (): ?string => null);
    config(['portal.default_locale' => 'om']);

    $this->withHeader('Accept-Language', '')->get('/')->assertRedirect('/om');
});

it('sends "/" to the browser language', function (string $header, string $target) {
    $this->withHeader('Accept-Language', $header)->get('/')->assertRedirect($target);
})->with([
    ['en-US,en;q=0.9', '/en'],
    ['om-ET,om;q=0.9,en;q=0.8', '/om'],
    ['fr-FR,fr;q=0.9,am;q=0.5', '/am'],
    ['fr', '/am'],
]);

it('prefers the remembered choice over the browser language', function () {
    $this->withUnencryptedCookie(Locales::COOKIE, 'om')
        ->withHeader('Accept-Language', 'en-US')
        ->get('/')
        ->assertRedirect('/om');
});

it('ignores a tampered language cookie', function () {
    $this->withUnencryptedCookie(Locales::COOKIE, 'xx')
        ->withHeader('Accept-Language', 'en')
        ->get('/')
        ->assertRedirect('/en');
});

it('keeps campaign parameters, in their order, when redirecting', function () {
    $this->withHeader('Accept-Language', '')
        ->get('/?utm_source=telegram&utm_campaign=launch')
        ->assertRedirect('/am?utm_source=telegram&utm_campaign=launch');
});

it('never leaks route internals into language links', function () {
    $this->get('/om')->assertDontSee('view=pages.home', escape: false)->assertDontSee('status=200', escape: false);
});

it('serves the home page in every language with the right lang attribute', function (string $locale) {
    $this->get("/{$locale}")
        ->assertOk()
        ->assertSee('<html lang="'.$locale.'">', escape: false)
        ->assertSee(__('site.name', [], $locale));
})->with(['en', 'am', 'om', 'har']);

it('shows Amharic on the Harari page until Harari is translated, never raw keys', function () {
    $this->get('/har')
        ->assertOk()
        ->assertSee(__('site.name', [], 'am'))
        ->assertDontSee('site.name')
        ->assertDontSee('site.office');
});

it('returns 404 for languages the portal does not serve', function (string $path) {
    $this->get($path)->assertNotFound();
})->with(['/fr', '/EN', '/amh', '/about']);

it('lists every language version and the default for search engines', function () {
    $response = $this->get('/om')->assertOk();

    foreach (['en', 'am', 'om', 'har'] as $locale) {
        $response->assertSee('<link rel="alternate" hreflang="'.$locale.'" href="'.url("/{$locale}").'">', escape: false);
    }
    $response->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/am').'">', escape: false)
        ->assertSee('<link rel="canonical" href="'.url('/om').'">', escape: false);
});

it('offers every language in its own script and marks the current one', function () {
    $this->get('/om')
        ->assertOk()
        ->assertSee('lang="am" hreflang="am"', escape: false)
        ->assertSee('አማርኛ')
        ->assertSee('Afaan Oromoo')
        ->assertSee('ሐረሪ')
        ->assertSee('English')
        ->assertSee('aria-current="true"', escape: false)
        ->assertSee('aria-label="'.__('nav.language', [], 'om').'"', escape: false);
});

it('keeps links in the language of the page', function () {
    $html = (string) $this->get('/om')->getContent();

    expect($html)->toMatch('#<a href="'.preg_quote(url('/om'), '#').'"\s+aria-current="page"#')
        ->not->toMatch('#<a href="'.preg_quote(url('/en'), '#').'"\s+aria-current="page"#');
});

it('remembers the language a visitor chose', function () {
    $this->get('/om')->assertPlainCookie(Locales::COOKIE, 'om');
});

it('does not set the cookie again when it already matches', function () {
    $this->withUnencryptedCookie(Locales::COOKIE, 'om')->get('/om')->assertCookieMissing(Locales::COOKIE);
});
