<?php

use App\Domain\Translation\Support\Locales;
use App\Services\Calendar\EthiopianCalendar;
use App\Settings\SiteSettings;

it('gives every page landmarks, a skip link and a labelled main menu', function () {
    $html = (string) $this->get('/en')->getContent();

    expect($html)->toContain('<a href="#content"', __('nav.skip', [], 'en'))
        ->toContain('<header', '<main id="content"', '<footer')
        ->toContain('<nav aria-label="'.__('nav.main', [], 'en').'"')
        ->toContain('<nav aria-label="'.__('nav.language', [], 'en').'"')
        ->and(strpos($html, 'href="#content"'))->toBeLessThan(strpos($html, '<header'));
});

it('links every language version and the default for search engines', function () {
    $html = (string) $this->get('/om')->getContent();

    expect(substr_count($html, 'rel="alternate" hreflang='))->toBe(5)
        ->and($html)->toContain('hreflang="x-default"', '<link rel="canonical" href="'.url('/om').'"');
});

it('preloads the Ethiopic font only on Ethiopic pages', function () {
    expect((string) $this->get('/am')->getContent())->toContain('noto-sans-ethiopic-wght-v5.woff2')
        ->and((string) $this->get('/har')->getContent())->toContain('noto-sans-ethiopic-wght-v5.woff2')
        ->and((string) $this->get('/en')->getContent())->not->toContain('rel="preload" href="/fonts/portal/noto')
        ->toContain('inter-latin-wght-v5.woff2');
});

it('has a description and today in both calendars', function () {
    $this->get('/en')
        ->assertSee('<meta name="description" content="'.e(__('site.description', [], 'en')).'">', escape: false)
        ->assertSee('E.C.');
});

it('shows contact details and social links from the site settings in the footer', function () {
    $settings = app(SiteSettings::class);
    $settings->contact_email = 'info@example.org';
    $settings->contact_phone = '+251 25 666 0000';
    $settings->hotline = '6070';
    $settings->address = ['en' => 'Harar, Jugol (sample)', 'am' => 'ሐረር፣ ጁጎል (ናሙና)'];
    $settings->social_links = ['telegram' => 'https://t.me/example', 'facebook' => 'javascript:alert(1)'];
    $settings->save();

    $this->get('/har')
        ->assertSee('ሐረር፣ ጁጎል (ናሙና)')
        ->assertSee('href="tel:+251256660000"', escape: false)
        ->assertSee('href="tel:6070"', escape: false)
        ->assertSee('href="mailto:info@example.org"', escape: false)
        ->assertSee('href="https://t.me/example"', escape: false)
        ->assertDontSee('javascript:alert', escape: false)
        ->assertDontSee(__('footer.contact_soon', [], 'am'));
});

it('says contact details are coming when none are set', function () {
    $this->get('/en')
        ->assertSee(__('footer.contact_soon', [], 'en'))
        ->assertDontSee(__('footer.follow', [], 'en'));
});

it('shows the year in both calendars and marks the test site outside production', function () {
    $this->get('/en')
        ->assertSee(__('footer.copyright', ['years' => app(EthiopianCalendar::class)->years(now()), 'name' => __('site.name', [], 'en')], 'en'))
        ->assertSee(__('footer.test_site', [], 'en'));
});

it('offers the four languages on the home page, each in its own language', function () {
    $html = (string) $this->get('/en')->getContent();

    foreach (Locales::all() as $locale) {
        expect($html)->toContain('lang="'.$locale.'" hreflang="'.$locale.'"')
            ->toContain(e(Locales::nativeName($locale)));
    }
});

it('still renders the footer when the settings cannot be read', function () {
    app()->bind(SiteSettings::class, fn () => throw new RuntimeException('settings unavailable'));

    $this->withUnencryptedCookie('hpp_locale', 'en')->get('/en')
        ->assertOk()
        ->assertSee(__('footer.contact_soon', [], 'en'));
});
