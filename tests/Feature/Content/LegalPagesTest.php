<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\PageTranslation;
use App\Domain\Content\Support\LegalPages;

it('creates the legal pages as published drafts in three languages and links them in the footer', function () {
    staff(SystemRole::SuperAdmin);

    $this->artisan('portal:legal-pages')->assertSuccessful();

    expect(Page::query()->count())->toBe(3)
        ->and(PageTranslation::query()->where('slug', 'privacy')->pluck('locale')->sort()->values()->all())->toBe(['am', 'en', 'om']);

    $this->get('/en/privacy')->assertOk()
        ->assertSee('Privacy policy')
        ->assertSee('Draft for legal review')
        ->assertSee('Proclamation No. 1321/2024');
    $this->get('/am/accessibility')->assertOk()->assertSee('የተደራሽነት መግለጫ')->assertSee('ለሕግ ግምገማ የቀረበ ረቂቅ');
    $this->get('/om/terms')->assertOk()->assertSee('Haalota itti fayyadamaa');

    $this->get('/en/news')->assertSee(route('page.show', ['locale' => 'en', 'path' => 'privacy']), escape: false)
        ->assertSee(route('page.show', ['locale' => 'en', 'path' => 'terms']), escape: false)
        ->assertSee(route('page.show', ['locale' => 'en', 'path' => 'accessibility']), escape: false);
});

it('never overwrites pages editors have changed, nor links them twice', function () {
    staff(SystemRole::SuperAdmin);
    $this->artisan('portal:legal-pages')->assertSuccessful();
    PageTranslation::query()->where('locale', 'en')->where('slug', 'privacy')->update(['title' => 'Sample edited privacy policy']);

    $this->artisan('portal:legal-pages')->expectsOutputToContain('privacy: already there')->assertSuccessful();

    expect(Page::query()->count())->toBe(3)
        ->and(PageTranslation::query()->where('locale', 'en')->where('slug', 'privacy')->value('title'))->toBe('Sample edited privacy policy')
        ->and(Menu::query()->where('location', 'footer')->value('items'))->toHaveCount(3);
});

it('has the same sections in every language', function () {
    foreach (LegalPages::all() as $slug => $languages) {
        $counts = array_map(fn (array $page): array => array_map(fn (array $section): int => count($section[1]), $page['sections']), $languages);
        expect(array_unique(array_map('json_encode', $counts)))->toHaveCount(1, "{$slug} differs between languages");
    }
});

it('needs a Super Admin to record as the author', function () {
    $this->artisan('portal:legal-pages')->assertFailed();
});
