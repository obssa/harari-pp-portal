<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePage;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Redirect;
use App\Filament\Resources\Menus\Pages\ManageMenus;
use App\Filament\Resources\Pages\Pages\CreatePage;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

/**
 * @param  array<string, array<string, mixed>>  $translations
 */
function makePage(array $translations, ?Page $parent = null, string $template = 'default'): Page
{
    return app(SavePage::class)->handle(new Page, [
        'template' => $template,
        'parent_id' => $parent?->id,
        'translations' => $translations,
    ], staff(SystemRole::Publisher));
}

it('serves published pages at their nested address in each language', function () {
    $about = makePage(['en' => ['title' => 'About us', 'published' => true], 'am' => ['title' => 'ስለ እኛ', 'published' => true]]);
    $history = makePage(['en' => ['title' => 'History', 'excerpt' => 'From HNL to PP', 'published' => true]], $about);

    expect($history->path('en'))->toBe('about-us/history')
        ->and($history->path('am'))->toBeNull();

    $this->get('/en/about-us/history')
        ->assertOk()
        ->assertSee('History')
        ->assertSee('From HNL to PP')
        ->assertSee('href="'.url('/en/about-us').'"', escape: false);

    $this->get('/en/about-us')->assertOk()->assertSee(__('pages.in_this_section', [], 'en'))->assertSee('/en/about-us/history');
    $this->get('/am/'.$about->translation('am')?->slug)->assertOk()->assertSee('ስለ እኛ');
});

it('redirects a wrong parent path and hides drafts', function () {
    $about = makePage(['en' => ['title' => 'About', 'published' => true]]);
    makePage(['en' => ['title' => 'Vision', 'published' => true]], $about);
    makePage(['en' => ['title' => 'Secret draft']]);

    $this->get('/en/vision')->assertRedirect('/en/about/vision')->assertStatus(301);
    $this->get('/en/secret-draft')->assertNotFound();
    $this->get('/en/news')->assertOk();
});

it('redirects the old address when a published page is renamed', function () {
    $about = makePage(['en' => ['title' => 'About', 'published' => true]]);
    $vision = makePage(['en' => ['title' => 'Vision', 'published' => true]], $about);
    $draft = makePage(['en' => ['title' => 'Plans']], $about);

    $vision->translation('en')?->update(['slug' => 'our-vision']);
    $draft->translation('en')?->update(['slug' => 'our-plans']);

    $this->get('/en/about/vision')->assertRedirect('/en/about/our-vision')->assertStatus(301);
    $this->get('/en/about/our-vision')->assertOk();
    expect(Redirect::query()->where('from_path', '/en/about/plans')->exists())->toBeFalse();

    $about->translation('en')?->update(['slug' => 'who-we-are']);
    $this->get('/en/about')->assertRedirect('/en/who-we-are')->assertStatus(301);
    $this->get('/en/about/our-vision')->assertRedirect('/en/who-we-are/our-vision');
});

it('keeps pages off the addresses of fixed sections', function () {
    $news = makePage(['en' => ['title' => 'News', 'published' => true], 'am' => ['title' => 'Contact', 'slug' => 'contact', 'published' => true]]);

    expect($news->translation('en')?->slug)->toBe('news-page')
        ->and($news->translation('am')?->slug)->toBe('contact-page');
    $this->get('/en/news-page')->assertOk();
    $this->get('/en/news')->assertOk()->assertSee(__('news.description', [], 'en'));
});

it('lists published pages in the sitemaps with their other languages', function () {
    $about = makePage(['en' => ['title' => 'About', 'published' => true], 'om' => ['title' => "Waa'ee keenya", 'published' => true]]);
    makePage(['en' => ['title' => 'Hidden draft']]);
    $om = $about->translation('om', fallback: false)?->slug;

    $this->get('/sitemaps/en.xml')
        ->assertOk()
        ->assertSee('<loc>'.url('/en/about').'</loc>', escape: false)
        ->assertSee('hreflang="om" href="'.url('/om/'.$om).'"', escape: false)
        ->assertDontSee('hidden-draft');
});

it('lets only publishers publish pages', function () {
    $page = app(SavePage::class)->handle(new Page, ['translations' => ['en' => ['title' => 'Programme', 'published' => true]]], staff(SystemRole::Editor));

    expect($page->translation('en')?->status)->toBe(ContentStatus::Draft);
});

it('refuses loops and too-deep nesting', function () {
    $a = makePage(['en' => ['title' => 'A']]);
    $b = makePage(['en' => ['title' => 'B']], $a);
    $c = makePage(['en' => ['title' => 'C']], $b);

    expect(fn () => app(SavePage::class)->handle($a, ['parent_id' => $c->id, 'translations' => ['en' => ['title' => 'A']]], staff(SystemRole::Publisher)))
        ->toThrow(ValidationException::class)
        ->and(fn () => makePage(['en' => ['title' => 'D']], $c))->toThrow(ValidationException::class);
});

it('creates a page from the admin', function () {
    $this->actingAs(staff(SystemRole::Publisher));

    livewire(CreatePage::class)
        ->fillForm(['template' => 'wide', 'translations' => ['om' => ['title' => "Waa'ee keenya", 'published' => true]]])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = Page::query()->with('translations')->firstOrFail();
    expect($page->template)->toBe('wide')->and($page->translation('om', fallback: false)?->isLive())->toBeTrue();
});

it('builds the main menu from the admin, per language, hiding targets that are not live', function () {
    $about = makePage(['en' => ['title' => 'About', 'published' => true], 'am' => ['title' => 'ስለ እኛ']]);
    Menu::query()->create(['location' => 'header', 'items' => [
        ['type' => 'news', 'labels' => ['en' => 'News', 'am' => 'ዜና']],
        ['type' => 'page', 'page_id' => $about->id, 'labels' => ['en' => 'About us', 'am' => 'ስለ እኛ'], 'children' => [
            ['type' => 'url', 'url' => 'https://example.org/report', 'labels' => ['en' => 'Annual report']],
        ]],
        ['type' => 'url', 'url' => 'javascript:alert(1)', 'labels' => ['en' => 'Bad link']],
    ]]);

    $en = (string) $this->get('/en')->getContent();
    expect($en)->toContain('>News</a>', 'About us', 'https://example.org/report')->not->toContain('Bad link');

    $am = (string) $this->get('/am')->getContent();
    expect($am)->toContain('ዜና')->not->toContain('About us');
});

it('falls back to Home and News until a main menu exists', function () {
    $this->get('/om')->assertSee(__('nav.news', [], 'om'))->assertSee(__('nav.home', [], 'om'));
});

it('lets publishers edit menus and keeps them from authors', function () {
    $this->actingAs(staff(SystemRole::Author))->get('/admin/menus')->assertForbidden();

    $this->actingAs(staff(SystemRole::Publisher));
    livewire(ManageMenus::class)
        ->callAction('create', data: ['location' => 'footer', 'items' => [['type' => 'news', 'labels' => ['en' => 'All news']]]])
        ->assertHasNoActionErrors();

    expect(Menu::query()->where('location', 'footer')->firstOrFail()->items[0]['labels']['en'] ?? null)->toBe('All news');
});
