<?php

it('renders the holding page', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<html lang="en">', escape: false)
        ->assertSee(__('holding.title', [], 'en'))
        ->assertSee(__('holding.notice', [], 'en'));
});

it('shows the message in English, Amharic and Afaan Oromo with the right lang attributes', function (string $locale) {
    $this->get('/')
        ->assertOk()
        ->assertSee('lang="'.$locale.'"', escape: false)
        ->assertSee(__('holding.title', [], $locale))
        ->assertSee(__('site.office', [], $locale));
})->with(['en', 'am', 'om']);

it('renders Ethiopic text unescaped and intact', function () {
    $this->get('/')->assertSee('የሐረሪ ክልል ቅርንጫፍ ጽሕፈት ቤት');
});
