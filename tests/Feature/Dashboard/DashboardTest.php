<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Domain\Dashboard\Models\Announcement;
use App\Domain\Dashboard\Models\DashboardLayout;
use App\Domain\Dashboard\Support\DashboardFilters;
use App\Filament\Pages\Dashboard;
use App\Filament\Support\DashboardRegistry;
use App\Filament\Widgets\AttentionNeeded;
use App\Filament\Widgets\EditorialPipeline;
use App\Filament\Widgets\KpiStrip;
use App\Filament\Widgets\QueueHealth;
use App\Filament\Widgets\TranslationCoverage;
use Carbon\CarbonImmutable;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use function Pest\Livewire\livewire;

/**
 * @param  list<string>  $publish
 */
function dashboardPost(string $title, array $publish = ['en'], ?string $submit = null): Post
{
    $publisher = staff(SystemRole::Publisher);
    $post = app(SavePost::class)->handle(new Post, ['type' => 'news', 'translations' => ['en' => ['title' => $title], 'am' => ['title' => $title.' (am)']]], $publisher);
    foreach ($publish as $locale) {
        app(ContentWorkflow::class)->apply($post, $locale, Transition::Publish, $publisher);
    }
    if ($submit !== null) {
        app(ContentWorkflow::class)->apply($post, $submit, Transition::Submit, $publisher);
    }

    return $post->refresh();
}

/**
 * @return list<string>
 */
function layoutKeys(SystemRole $role): array
{
    return array_column(DashboardRegistry::layoutFor(staff($role)), 'key');
}

it('starts each role with the widgets it may see', function () {
    expect(layoutKeys(SystemRole::Publisher))->toBe(['announcements', 'kpis', 'attention', 'quick', 'pipeline', 'coverage', 'translation_queue', 'search', 'traffic', 'top_pages', 'media'])
        ->and(layoutKeys(SystemRole::Admin))->toContain('queues', 'activity', 'media')
        ->and(layoutKeys(SystemRole::Author))->not->toContain('coverage', 'translation_queue', 'search', 'queues', 'activity')
        ->and(layoutKeys(SystemRole::MediaManager))->toContain('media')->not->toContain('kpis');
});

it('shows every staff role its dashboard', function (SystemRole $role) {
    $this->actingAs(staff($role))->get('/admin')->assertOk();
})->with([SystemRole::SuperAdmin, SystemRole::Admin, SystemRole::Publisher, SystemRole::Editor, SystemRole::Author, SystemRole::Translator, SystemRole::Analyst, SystemRole::SupportAgent]);

it('lets people save their own layout and keeps out widgets they may not see', function () {
    $author = staff(SystemRole::Author);
    $this->actingAs($author);

    livewire(Dashboard::class)
        ->callAction('customise', data: ['widgets' => [['key' => 'quick', 'size' => 12], ['key' => 'pipeline', 'size' => 8]]])
        ->assertHasNoActionErrors();
    expect(DashboardRegistry::layoutFor($author))->toBe([['key' => 'quick', 'size' => 12], ['key' => 'pipeline', 'size' => 8]]);

    // The form refuses other widgets and widths; the registry drops them too.
    livewire(Dashboard::class)
        ->callAction('customise', data: ['widgets' => [['key' => 'queues', 'size' => 6]]])
        ->assertHasActionErrors(['widgets.0.key']);
    DashboardRegistry::save($author, [['key' => 'queues', 'size' => 6], ['key' => 'pipeline', 'size' => 5], 'junk']);
    expect(DashboardRegistry::layoutFor($author))->toBe([['key' => 'pipeline', 'size' => 8]]);

    livewire(Dashboard::class)->callAction('reset');
    expect(DashboardLayout::query()->where('user_id', $author->id)->exists())->toBeFalse()
        ->and(array_column(DashboardRegistry::layoutFor($author), 'key'))->toContain('announcements', 'quick');
});

it('reads the range, language and office filters', function () {
    CarbonImmutable::setTestNow('2026-10-19 10:00:00');

    $filters = DashboardFilters::from(['range' => '7', 'locale' => 'am', 'office' => 'x']);
    expect($filters->from->toDateString())->toBe('2026-10-12')
        ->and($filters->locale)->toBe('am')
        ->and($filters->officeId)->toBe('x')
        ->and($filters->previousFrom()->toDateTimeString())->toBe('2026-10-04 14:00:00')
        ->and(DashboardFilters::from(['range' => 'efy', 'locale' => 'fr'])->from->toDateString())->toBe('2026-07-08')
        ->and(DashboardFilters::from(['range' => 'efy'])->locale)->toBeNull()
        ->and(DashboardFilters::fiscalYearStart(CarbonImmutable::parse('2026-03-01'))->toDateString())->toBe('2025-07-08');

    CarbonImmutable::setTestNow();
});

it('counts published posts, reviews and missing translations', function () {
    $this->actingAs(staff(SystemRole::Publisher));
    dashboardPost('Sample one');
    dashboardPost('Sample two', ['en', 'am']);
    dashboardPost('Sample three', [], submit: 'en');

    livewire(KpiStrip::class, ['pageFilters' => ['range' => '30']])
        ->assertSeeInOrder([__('dashboard.kpi.published'), '3', __('dashboard.kpi.in_review'), '1', __('dashboard.kpi.missing_translations'), '2']);
});

it('shows the editorial pipeline per language', function () {
    $this->actingAs(staff(SystemRole::Publisher));
    dashboardPost('Sample pipeline', ['en']);

    $pipeline = livewire(EditorialPipeline::class, ['pageFilters' => []])->instance()->pipeline();
    $published = collect($pipeline['rows'])->firstWhere('status.value', 'published');
    $drafts = collect($pipeline['rows'])->firstWhere('status.value', 'draft');

    expect($published['counts']['en'])->toBe(1)->and($published['counts']['am'])->toBe(0)
        ->and($drafts['counts']['am'])->toBe(1);
});

it('lists what needs attention only to those who can act on it', function () {
    $post = dashboardPost('Sample waiting', [], submit: 'en');
    PostTranslation::query()->where('post_id', $post->id)->where('locale', 'en')->update(['updated_at' => now()->subDays(2)]);

    $this->actingAs(staff(SystemRole::Publisher));
    livewire(AttentionNeeded::class, ['pageFilters' => []])->assertSee(__('dashboard.attention.review_waiting'));

    $this->actingAs(staff(SystemRole::Author));
    livewire(AttentionNeeded::class, ['pageFilters' => []])->assertSee(__('dashboard.attention.none'));
});

it('reports background work and the scheduler', function () {
    $this->actingAs(staff(SystemRole::Admin));
    DB::table('jobs')->insert(['queue' => 'media', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->subMinutes(30)->timestamp]);

    $health = livewire(QueueHealth::class, ['pageFilters' => []])->instance()->health();
    expect($health['queues'][0]['waiting'])->toBe(1)->and($health['healthy'])->toBeFalse();

    DB::table('jobs')->delete();
    Cache::forever(QueueHealth::HEARTBEAT, now()->toIso8601String());
    expect(livewire(QueueHealth::class, ['pageFilters' => []])->instance()->health()['healthy'])->toBeTrue();
});

it('shows translation coverage of the interface and of published posts', function () {
    $this->actingAs(staff(SystemRole::Admin));
    dashboardPost('Sample coverage', ['en', 'am']);
    dashboardPost('Sample coverage two', ['en']);

    $rows = collect(livewire(TranslationCoverage::class, ['pageFilters' => []])->instance()->coverage())->keyBy('locale');
    expect($rows['en']['interface'])->toBe(100.0)
        ->and($rows['am']['interface'])->toBe(100.0)
        ->and($rows['am']['posts'])->toBe(50.0)
        ->and($rows['om']['posts'])->toBe(0.0);
});

it('shows current announcements to all staff, written by Super Admins only', function () {
    Announcement::query()->create(['title' => 'Sample notice for staff', 'tone' => 'warning']);
    Announcement::query()->create(['title' => 'Sample expired notice', 'ends_at' => now()->subDay()]);
    Announcement::query()->create(['title' => 'Sample future notice', 'starts_at' => now()->addDay()]);

    $this->actingAs(staff(SystemRole::Author))->get('/admin')
        ->assertSee('Sample notice for staff')
        ->assertDontSee('Sample expired notice')
        ->assertDontSee('Sample future notice');

    $this->actingAs(staff(SystemRole::Admin))->get('/admin/announcements')->assertForbidden();
    $this->actingAs(staff(SystemRole::SuperAdmin))->get('/admin/announcements')->assertOk()->assertSee('Sample expired notice');
});

it('gives each widget the width of the layout', function () {
    $admin = staff(SystemRole::Admin);
    $widgets = DashboardRegistry::widgetsFor($admin);
    $queues = collect($widgets)->first(fn (WidgetConfiguration $w): bool => $w->widget === QueueHealth::class);
    $this->actingAs($admin);

    expect($queues?->getProperties()['span'] ?? null)->toBe(['default' => 12, 'lg' => QueueHealth::defaultSize()])
        ->and(Pest\Livewire\livewire(QueueHealth::class, ['span' => ['default' => 12, 'lg' => 4]])->instance()->getColumnSpan())
        ->toBe(['default' => 12, 'lg' => 4]);
});
