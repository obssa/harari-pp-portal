<?php

const PORTAL_LOCALES = ['en', 'am', 'om', 'har'];

function langFile(string $locale): array
{
    $path = lang_path($locale.'.json');
    expect($path)->toBeFile();

    return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
}

it('has valid JSON for every portal locale', function (string $locale) {
    expect(langFile($locale))->toBeArray()->not->toBeEmpty();
})->with(PORTAL_LOCALES);

it('lists exactly the English keys in every other locale', function (string $locale) {
    expect(array_keys(langFile($locale)))->toEqualCanonicalizing(array_keys(langFile('en')));
})->with(['am', 'om', 'har']);

it('has a non-empty value for every key in en, am and om', function (string $locale) {
    $empty = array_keys(array_filter(langFile($locale), fn ($v) => ! is_string($v) || trim($v) === ''));

    expect($empty)->toBe([]);
})->with(['en', 'am', 'om']);

it('keeps Harari values as strings so translators can fill them in', function () {
    foreach (langFile('har') as $key => $value) {
        expect($value)->toBeString("har value for {$key} must be a string");
    }
});

it('keeps placeholders identical to English in translated strings', function (string $locale) {
    $en = langFile('en');
    foreach (langFile($locale) as $key => $value) {
        if ($value === '') {
            continue;
        }
        preg_match_all('/:[a-z_]+/i', $en[$key], $expected);
        preg_match_all('/:[a-z_]+/i', $value, $actual);
        expect($actual[0])->toEqualCanonicalizing($expected[0]);
    }
})->with(['am', 'om', 'har']);

it('stores Ethiopic text as UTF-8 Ethiopic script in Amharic', function () {
    expect(langFile('am')['site.office'])->toMatch('/\p{Ethiopic}/u');
});
