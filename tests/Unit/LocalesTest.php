<?php

use App\Domain\Translation\Support\Locales;
use App\Domain\Translation\Support\TranslationCatalog;

it('picks the best supported language from Accept-Language', function (?string $header, ?string $expected) {
    expect(Locales::fromAcceptLanguage($header))->toBe($expected);
})->with([
    'english browser' => ['en-US,en;q=0.9', 'en'],
    'amharic regional tag' => ['am-ET,am;q=0.9,en;q=0.8', 'am'],
    'oromo first' => ['om-ET,om;q=0.9,en;q=0.8', 'om'],
    'unsupported first, amharic later' => ['fr-FR,fr;q=0.9,am;q=0.5', 'am'],
    'quality beats order' => ['en;q=0.4,om;q=0.8', 'om'],
    'equal quality keeps browser order' => ['om,en', 'om'],
    'harari' => ['har', 'har'],
    'q=0 means never' => ['en;q=0,am;q=0.1', 'am'],
    'underscore variant' => ['am_ET', 'am'],
    'nothing supported' => ['fr,de', null],
    'wildcard only' => ['*', null],
    'empty header' => ['', null],
    'no header' => [null, null],
]);

it('follows the fallback chain from PLAN.md section 6', function () {
    expect(Locales::fallbackChain('har'))->toBe(['har', 'am', 'en'])
        ->and(Locales::fallbackChain('om'))->toBe(['om', 'en'])
        ->and(Locales::fallbackChain('am'))->toBe(['am', 'en'])
        ->and(Locales::fallbackChain('en'))->toBe(['en']);
});

it('prefers the admin setting, then config, then the first locale', function () {
    Locales::resolveDefaultUsing(fn (): ?string => 'om');
    config(['portal.default_locale' => 'am']);
    expect(Locales::default())->toBe('om');

    Locales::resolveDefaultUsing(fn (): ?string => null);
    expect(Locales::default())->toBe('am');

    Locales::resolveDefaultUsing(fn (): ?string => 'fr');
    config(['portal.default_locale' => 'fr']);
    expect(Locales::default())->toBe('en');
});

it('names each language in its own script', function () {
    expect(Locales::nativeName('am'))->toBe('አማርኛ')
        ->and(Locales::nativeName('om'))->toBe('Afaan Oromoo')
        ->and(Locales::nativeName('xx'))->toBe('xx');
});

it('checks placeholders and plural parts of a translation', function (string $en, string $translation, bool $ok) {
    expect(TranslationCatalog::placeholdersMatch($en, $translation))->toBe($ok);
})->with([
    'same placeholder' => ['Published on :date', 'የታተመው :date', true],
    'oromo suffix on placeholder' => [':attribute must be accepted.', ':attributen fudhatama argachuu qabu.', true],
    'missing placeholder' => ['Published on :date', 'የታተመው', false],
    'invented placeholder' => ['Hello', 'Hello :name', false],
    'plural parts kept' => ['{1} :min minute|[2,*] :min minutes', '{1} የ:min ደቂቃ|[2,*] የ:min ደቂቃዎች', true],
    'plural part lost' => ['{1} :min minute|[2,*] :min minutes', 'የ:min ደቂቃ', false],
]);
