<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Domain\Homepage\Actions\SaveHomepage;
use App\Domain\Homepage\Enums\SectionKey;
use App\Domain\Homepage\Models\HomepageSection;
use App\Domain\Homepage\Support\HomepageLayout;
use App\Domain\Organisation\Actions\SaveLeader;
use App\Domain\Organisation\Actions\SaveLeague;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\League;
use App\Filament\Pages\ManageHomepage;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

/**
 * @param  array<string, array<string, mixed>>  $translations
 * @param  array<string, mixed>  $data
 */
function homePost(array $translations, array $data = []): Post
{
    $publisher = staff(SystemRole::Publisher);
    $post = app(SavePost::class)->handle(new Post, ['type' => 'news', ...$data, 'translations' => $translations], $publisher);
    foreach (array_keys($translations) as $locale) {
        app(ContentWorkflow::class)->apply($post, $locale, Transition::Publish, $publisher);
    }

    return $post->refresh();
}

/**
 * The builder's state with changes for some sections, in the given order.
 *
 * @param  array<string, array<string, mixed>>  $changes  key => fields
 * @param  list<string>|null  $order
 */
function arrangeHomepage(array $changes, ?array $order = null): void
{
    $states = HomepageLayout::all()->mapWithKeys(fn (HomepageSection $s): array => [$s->key->value => ManageHomepage::state($s)])->all();
    foreach ($changes as $key => $fields) {
        $states[$key] = [...$states[$key], ...$fields];
    }
    if ($order !== null) {
        $first = [];
        foreach ($order as $key) {
            $first[$key] = $states[$key];
        }
        $states = [...$first, ...array_diff_key($states, $first)];
    }

    app(SaveHomepage::class)->handle(array_values($states), staff(SystemRole::Publisher));
}

it('creates every section once with sensible defaults', function () {
    $sections = HomepageLayout::all();
    HomepageLayout::all();

    expect(HomepageSection::query()->count())->toBe(count(SectionKey::cases()))
        ->and($sections->firstWhere('key', SectionKey::Ticker)?->enabled)->toBeFalse()
        ->and($sections->firstWhere('key', SectionKey::LatestNews)?->enabled)->toBeTrue()
        ->and($sections->firstWhere('key', SectionKey::Values)?->setting('items'))->toHaveCount(count(HomepageLayout::DEFAULT_VALUES));
});

it('shows a welcome, the latest news, press and the core values on a fresh site', function () {
    homePost(['en' => ['title' => 'Sample council session news']]);
    homePost(['en' => ['title' => 'Sample statement on the budget']], ['type' => 'statement']);

    $this->get('/en')
        ->assertOk()
        ->assertSee(__('site.description', [], 'en'))
        ->assertSeeInOrder([
            __('homepage.section.latest_news', [], 'en'),
            'Sample council session news',
            __('homepage.section.press', [], 'en'),
            'Sample statement on the budget',
            __('homepage.section.values', [], 'en'),
            __('homepage.value.medemer', [], 'en'),
        ])
        ->assertDontSee(__('homepage.section.ticker', [], 'en'));
});

it('puts featured or hand-picked posts in the hero, in the chosen order', function () {
    $first = homePost(['en' => ['title' => 'Sample first pick']]);
    $second = homePost(['en' => ['title' => 'Sample second pick']]);
    homePost(['en' => ['title' => 'Sample featured story']], ['is_featured' => true]);

    // One slide: no slide links.
    $this->get('/en')->assertSee('Sample featured story')->assertSee('id="slide-1"', escape: false)->assertDontSee('href="#slide-1"', escape: false);

    arrangeHomepage(['hero' => ['curated_ids' => [$second->id, $first->id, 'missing-id']]]);
    expect(HomepageSection::query()->where('key', 'hero')->value('curated_ids'))->toBe([$second->id, $first->id]);

    $this->get('/en')
        ->assertSeeInOrder(['id="slide-1"', 'Sample second pick', 'id="slide-2"', 'Sample first pick'], escape: false)
        ->assertSee(__('homepage.slide', ['number' => 2, 'title' => 'Sample first pick'], 'en'));
});

it('reorders, switches off and limits sections, and hides them per language', function () {
    foreach (range(1, 4) as $n) {
        homePost(['en' => ['title' => "Sample item {$n}"]]);
    }

    arrangeHomepage([
        'latest_news' => ['limit' => 2, 'titles' => ['en' => 'From the branch']],
        'press' => ['enabled' => false],
        'values' => ['locales' => ['en', 'am']],
    ], ['values', 'latest_news']);

    $html = (string) $this->get('/en')
        ->assertOk()
        ->assertSeeInOrder([__('homepage.value.medemer', [], 'en'), 'From the branch'])
        ->assertDontSee(__('homepage.section.press', [], 'en'))
        ->getContent();
    expect(substr_count($html, '>Sample item '))->toBe(2);

    $this->get('/am')->assertSee(__('homepage.value.medemer', [], 'am'));
    $this->get('/om')->assertDontSee(__('homepage.value.medemer', [], 'om'));
});

it('shows the ticker, leadership message, counters, leagues and partner links once set up', function () {
    $news = homePost(['en' => ['title' => 'Sample breaking item']]);
    $message = homePost(['en' => ['title' => 'Sample full message']], ['type' => 'speech']);
    $leader = app(SaveLeader::class)->handle(new Leader, ['body' => 'executive_committee', 'translations' => ['en' => ['name' => 'Sample Chair', 'position' => 'Sample head', 'published' => true]]], staff(SystemRole::Publisher));
    app(SaveLeague::class)->handle(new League, ['translations' => ['en' => ['name' => 'Sample Youth League', 'published' => true]]], staff(SystemRole::Publisher));

    arrangeHomepage([
        'ticker' => ['enabled' => true, 'curated_ids' => [$news->id]],
        'leadership' => ['enabled' => true, 'leader_id' => $leader->id, 'quote' => ['en' => 'Sample quote for the homepage.'], 'post_id' => $message->id],
        'counters' => ['enabled' => true, 'counter_items' => [['value' => '1,200', 'label' => ['en' => 'Sample members']], ['value' => '', 'label' => ['en' => 'Dropped']]]],
        'partners' => ['enabled' => true, 'partner_items' => [['url' => 'https://example.org', 'label' => ['en' => 'Sample partner']]]],
    ]);

    $this->get('/en')
        ->assertSee(__('homepage.section.ticker', [], 'en'))
        ->assertSee('Sample breaking item')
        ->assertSee('Sample quote for the homepage.')
        ->assertSee('/en/leaders/sample-chair')
        ->assertSee('/en/news/sample-full-message')
        ->assertSee('1,200')
        ->assertSee('Sample members')
        ->assertDontSee('Dropped')
        ->assertSee('Sample Youth League')
        ->assertSee('href="https://example.org"', escape: false);

    expect(fn () => arrangeHomepage(['partners' => ['partner_items' => [['url' => 'http://example.org', 'label' => ['en' => 'Plain']]]]]))
        ->toThrow(ValidationException::class);
});

it('lets publishers arrange the homepage from the admin and keeps it from editors', function () {
    $this->actingAs(staff(SystemRole::Editor))->get('/admin/homepage')->assertForbidden();

    $this->actingAs(staff(SystemRole::Publisher));
    $this->get('/admin/homepage')->assertOk()->assertSee(__('homepage.section.hero', [], 'en'));

    $component = livewire(ManageHomepage::class);
    $sections = $component->get('data.sections');
    $keys = array_keys($sections);
    $press = array_search('press', array_column($sections, 'key'), true);
    $component->set("data.sections.{$keys[$press]}.enabled", false)
        ->call('save')
        ->assertHasNoErrors();

    expect(HomepageSection::query()->where('key', 'press')->value('enabled'))->toBeFalsy();
});
