<?php

use App\Domain\Translation\FallbackTranslator;

/**
 * Adds flat JSON strings (dotted keys stay flat, as in lang/*.json).
 * Translator::addLines() would nest "t.k" into arrays, so set them directly.
 *
 * @param  array<string, array<string, string>>  $byLocale
 */
function useStrings(array $byLocale): void
{
    $translator = app('translator');
    foreach (array_keys($byLocale) as $locale) {
        $translator->load('*', '*', $locale);
    }
    $loaded = (fn () => $this->loaded)->call($translator);
    foreach ($byLocale as $locale => $lines) {
        $loaded['*']['*'][$locale] = [...($loaded['*']['*'][$locale] ?? []), ...$lines];
    }
    $translator->setLoaded($loaded);
}

it('is the translator the app uses', function () {
    expect(app('translator'))->toBeInstanceOf(FallbackTranslator::class);
});

it('shows Amharic for Harari strings nobody has translated yet', function () {
    expect(__('site.office', [], 'har'))->toBe(__('site.office', [], 'am'))
        ->and(__('site.office', [], 'har'))->not->toBe('site.office');
});

it('falls back from Harari to English when Amharic is missing too', function () {
    useStrings(['en' => ['t.only_en' => 'English only']]);

    expect(__('t.only_en', [], 'har'))->toBe('English only');
});

it('falls back from Afaan Oromo straight to English, never to Amharic', function () {
    useStrings(['en' => ['t.k' => 'English'], 'am' => ['t.k' => 'አማርኛ']]);

    expect(__('t.k', [], 'om'))->toBe('English');
});

it('treats empty strings as untranslated', function () {
    useStrings(['en' => ['t.empty' => 'English'], 'om' => ['t.empty' => '']]);

    expect(__('t.empty', [], 'om'))->toBe('English');
});

it('applies replacements to fallback strings', function () {
    useStrings(['am' => ['t.hello' => 'ሰላም :name']]);

    expect(__('t.hello', ['name' => 'Abebe'], 'har'))->toBe('ሰላም Abebe');
});

it('returns the key when no language has the string', function () {
    expect(__('t.nowhere', [], 'har'))->toBe('t.nowhere');
});

it('does not fall back when asked not to', function () {
    expect(app('translator')->get('site.office', [], 'har', false))->toBe('site.office');
});

it('falls back for Laravel framework messages too', function () {
    expect(__('validation.required', ['attribute' => 'X'], 'har'))
        ->toBe(__('validation.required', ['attribute' => 'X'], 'am'))
        ->and(__('validation.required', ['attribute' => 'X'], 'en'))->toBe('The X field is required.')
        ->and(__('validation.required', ['attribute' => 'X'], 'om'))->toContain('X')
        ->and(__('validation.required', ['attribute' => 'X'], 'om'))->not->toBe('The X field is required.');
});

it('keeps plural rules working through the fallback', function () {
    useStrings(['am' => ['t.minutes' => '{1} :count ደቂቃ|[2,*] :count ደቂቃዎች']]);

    expect(trans_choice('t.minutes', 1, [], 'har'))->toBe('1 ደቂቃ')
        ->and(trans_choice('t.minutes', 5, [], 'har'))->toBe('5 ደቂቃዎች');
});

it('remembers which strings were served from a fallback language', function () {
    __('site.office', [], 'har');
    __('site.office', [], 'am');

    expect(app('translator')->fallbacksUsed())->toBe(['har' => ['site.office' => 'am']]);
});
