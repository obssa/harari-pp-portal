<?php

use App\Domain\Translation\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

it('shows a translated 404 page in the language of the address', function () {
    $this->get('/am/no-such-page')
        ->assertNotFound()
        ->assertSee('<html lang="am">', escape: false)
        ->assertSee(__('errors.404.title', [], 'am'))
        ->assertSee('href="'.url('/am').'"', escape: false);
});

it('uses the remembered language for addresses without a language', function () {
    $this->withUnencryptedCookie(Locales::COOKIE, 'om')->get('/no-such-page')
        ->assertNotFound()
        ->assertSee(__('errors.404.title', [], 'om'));
});

it('uses the browser language when nothing is remembered', function () {
    $this->get('/no-such-page', ['Accept-Language' => 'en-GB,en;q=0.8'])
        ->assertNotFound()
        ->assertSee('<html lang="en">', escape: false)
        ->assertSee(__('errors.404.title', [], 'en'));
});

it('shows Amharic on Harari error pages until Harari is translated', function () {
    $this->get('/har/no-such-page')
        ->assertNotFound()
        ->assertSee('<html lang="har">', escape: false)
        ->assertSee(__('errors.404.title', [], 'am'));
});

it('renders a translated page for each error code', function (int $code) {
    Route::get('/en/__error-'.$code, fn () => abort($code));

    $this->get('/en/__error-'.$code)
        ->assertStatus($code)
        ->assertSee(__("errors.{$code}.title", [], 'en'))
        ->assertSee(__("errors.{$code}.body", [], 'en'));
})->with([403, 419, 429, 500, 503]);

it('shows the maintenance page in every language, since it is rendered before anyone asks', function () {
    $html = view('errors.503')->render();

    foreach (['en', 'am', 'om'] as $locale) {
        expect($html)->toContain('<section lang="'.$locale.'"')->toContain(e(__('errors.503.title', [], $locale)));
    }
    expect($html)->toContain('<section lang="har"');
});

it('picks the error-page language from the address, cookie, browser, then default', function () {
    $request = fn (string $uri, array $cookies = [], array $server = []) => Request::create($uri, 'GET', [], $cookies, [], $server);

    expect(Locales::fromRequest($request('/om/x')))->toBe('om')
        ->and(Locales::fromRequest($request('/x', [Locales::COOKIE => 'har'])))->toBe('har')
        ->and(Locales::fromRequest($request('/x', [Locales::COOKIE => 'fr'], ['HTTP_ACCEPT_LANGUAGE' => 'om-ET'])))->toBe('om')
        ->and(Locales::fromRequest($request('/x', [], ['HTTP_ACCEPT_LANGUAGE' => ''])))->toBe(Locales::default());
});
