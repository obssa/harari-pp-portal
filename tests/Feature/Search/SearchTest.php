<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePage;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Domain\Organisation\Actions\SaveLeader;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\Office;
use App\Domain\Search\Models\SearchTerm;
use App\Domain\Search\Support\SearchText;
use Illuminate\Support\Facades\DB;

/**
 * @param  array<string, array<string, mixed>>  $translations
 * @param  list<string>  $publish
 */
function searchPost(array $translations, array $publish = ['en']): Post
{
    $publisher = staff(SystemRole::Publisher);
    $post = app(SavePost::class)->handle(new Post, ['type' => 'news', 'translations' => $translations], $publisher);
    foreach ($publish as $locale) {
        app(ContentWorkflow::class)->apply($post, $locale, Transition::Publish, $publisher);
    }

    return $post->refresh();
}

it('makes alike-sounding Ethiopic letters and Latin accents match', function () {
    expect(SearchText::normalize('ሐረር'))->toBe(SearchText::normalize('ሀረር'))
        ->and(SearchText::normalize('ኀይል'))->toBe(SearchText::normalize('ሀይል'))
        ->and(SearchText::normalize('ሠላም'))->toBe('ሰላም')
        ->and(SearchText::normalize('ዓመት'))->toBe('አመት')
        ->and(SearchText::normalize('ፀሐይ'))->toBe('ጸሀይ')
        ->and(SearchText::normalize('ሰላም፡ለሁሉም።'))->toBe('ሰላም ለሁሉም')
        ->and(SearchText::normalize('Café ODA'))->toBe('cafe oda')
        ->and(SearchText::words('a ab ab ሀ ሐረር x'))->toBe(['ab', 'ሀረር'])
        ->and(SearchText::build(['መደመር']))->toContain('medemer');
});

it('finds posts by any of their words, in any order and spelling', function () {
    searchPost(['en' => ['title' => 'Youth league meeting held in Harar', 'excerpt' => 'Sample summary']]);
    searchPost(['am' => ['title' => 'የሐረር ክልል ምክር ቤት ስብሰባ']], ['am']);
    searchPost(['en' => ['title' => 'Unrelated sample item']]);

    $this->get('/en/search?q=harar+YOUTH')
        ->assertOk()
        ->assertSee('Youth league meeting held in Harar')
        ->assertDontSee('Unrelated sample item');

    $this->get('/am/search?q='.urlencode('ሀረር ስብሰባ'))->assertSee('የሐረር ክልል ምክር ቤት ስብሰባ');
});

it('finds Amharic text typed in Latin letters', function () {
    searchPost(['am' => ['title' => 'መደመር እና ልማት']], ['am']);

    $this->get('/am/search?q=medemer')->assertOk()->assertSee('መደመር እና ልማት');
});

it('only finds what is published, Amharic included for the other languages', function () {
    searchPost(['en' => ['title' => 'Sample draft budget']], []);
    searchPost(['am' => ['title' => 'የበጀት ውይይት ናሙና']], ['am']);

    $this->get('/en/search?q=budget')->assertOk()->assertSee(__('search.none', ['query' => 'budget'], 'en'));
    $this->get('/om/search?q='.urlencode('በጀት'))->assertSee('የበጀት ውይይት ናሙና')->assertSee('lang="am"', escape: false);
    $this->get('/har/search?q='.urlencode('በጀት'))->assertSee('የበጀት ውይይት ናሙና')->assertSee('lang="am"', escape: false);
});

it('finds pages, people and offices with links to them', function () {
    $publisher = staff(SystemRole::Publisher);
    app(SavePage::class)->handle(new Page, ['translations' => ['en' => ['title' => 'Sample history of the branch', 'published' => true]]], $publisher);
    app(SaveLeader::class)->handle(new Leader, ['body' => 'secretariat', 'translations' => ['en' => ['name' => 'Sample Branch Secretary', 'position' => 'Sample role', 'published' => true]]], $publisher);
    app(SaveOffice::class)->handle(new Office, ['type' => 'sector', 'translations' => ['en' => ['name' => 'Sample branch sector office', 'published' => true]]], $publisher);

    $this->get('/en/search?q=branch')
        ->assertOk()
        ->assertSeeInOrder([__('search.kind.pages', [], 'en'), 'Sample history of the branch', __('search.kind.people', [], 'en'), 'Sample Branch Secretary', __('search.kind.offices', [], 'en'), 'Sample branch sector office'])
        ->assertSee('/en/sample-history-of-the-branch')
        ->assertSee('/en/leaders/sample-branch-secretary')
        ->assertSee('/en/offices/sample-branch-sector-office');
});

it('limits each kind on the overview and links to the rest', function () {
    foreach (range(1, 7) as $n) {
        searchPost(['en' => ['title' => "Sample council report {$n}"]]);
    }

    $html = (string) $this->get('/en/search?q=council')->getContent();
    expect(substr_count($html, '>Sample council report '))->toBe(5)
        ->and($html)->toContain('type=news');

    $all = (string) $this->get('/en/search?q=council&type=news')->getContent();
    expect(substr_count($all, '>Sample council report '))->toBe(7);
});

it('counts searches and keeps them out of caches and search engines', function () {
    searchPost(['en' => ['title' => 'Sample water project']]);

    expect((string) $this->get('/en/search?q=Water')->headers->get('X-Robots-Tag'))->toContain('noindex');
    $response = $this->get('/en/search?q=water');
    $this->get('/en/search?q=zebra');
    $this->get('/en/search?q=x');

    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');
    expect(SearchTerm::query()->where('term', 'water')->first()?->searches)->toBe(2)
        ->and(SearchTerm::query()->where('term', 'zebra')->first()?->last_results)->toBe(0)
        ->and(SearchTerm::query()->count())->toBe(2);
});

it('shows the search insights to those who see analytics', function () {
    SearchTerm::query()->create(['locale' => 'en', 'term' => 'sample missing thing', 'searches' => 3, 'last_results' => 0, 'last_searched_at' => now()]);

    $this->actingAs(staff(SystemRole::Editor))->get('/admin/search-terms')->assertForbidden();
    $this->actingAs(staff(SystemRole::Analyst))->get('/admin/search-terms')->assertOk()->assertSee('sample missing thing');
});

it('offers search on every page and tells search engines about it', function () {
    $html = (string) $this->get('/en/news')->getContent();

    expect($html)->toContain('role="search"')
        ->toContain('action="'.url('/en/search').'"')
        ->toContain('"@type":"SearchAction"');
});

it('rebuilds the search text of existing records', function () {
    $post = searchPost(['en' => ['title' => 'Sample rebuilt item']]);
    $translation = $post->translation('en');
    DB::table('post_translations')->where('id', $translation?->id)->update(['search_text' => null]);

    $this->artisan('portal:search-reindex')->assertSuccessful();

    expect(PostTranslation::query()->find($translation?->id)?->search_text)->toContain('rebuilt');
});

it('slows down very busy searchers', function () {
    foreach (range(1, 30) as $n) {
        $this->get('/en/search?q=sample')->assertOk();
    }

    $this->get('/en/search?q=sample')->assertStatus(429);
});
