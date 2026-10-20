<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Homepage\Enums\SectionKey;
use App\Domain\Homepage\Models\HomepageSection;
use App\Domain\Media\Actions\SaveGallery;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Models\Gallery;
use App\Domain\Projects\Actions\SaveProject;
use App\Domain\Projects\Actions\SaveStatistic;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\Statistic;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Statistics\Pages\CreateStatistic;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestFiles;

use function Pest\Livewire\livewire;

function projectSector(string $name): Category
{
    $sector = Category::query()->create(['type' => Category::TYPE_SECTOR]);
    $sector->putTranslation('en', ['name' => $name]);

    return $sector;
}

/**
 * @param  array<string, array<string, mixed>>  $translations
 * @param  array<string, mixed>  $data
 */
function makeProject(array $translations, array $data = []): Project
{
    return app(SaveProject::class)->handle(new Project, ['translations' => $translations, ...$data], staff(SystemRole::Publisher));
}

/**
 * @param  array<string, array<string, mixed>>  $translations
 * @param  array<string, mixed>  $data
 */
function makeStatistic(array $translations, float|int|string $value, array $data = []): Statistic
{
    return app(SaveStatistic::class)->handle(new Statistic, ['translations' => $translations, 'value' => $value, ...$data], staff(SystemRole::Publisher));
}

it('saves projects with status, progress, dates, budget and a map pin, and checks them', function () {
    $sector = projectSector('Sample water');
    $postCategory = Category::query()->create(['type' => Category::TYPE_POST]);

    $project = makeProject(['en' => ['title' => 'Sample water supply', 'location' => 'Sample woreda', 'published' => true]], [
        'sector_id' => $sector->id, 'status' => 'completed', 'progress' => 40, 'starts_on' => '2025-01-10', 'ends_on' => '2026-06-30',
        'budget' => '1500000', 'latitude' => '9.31', 'longitude' => '42.12',
    ]);

    expect($project->status)->toBe(ProjectStatus::Completed)
        ->and($project->progress)->toBe(100)
        ->and($project->publicBudget())->toBeNull()
        ->and($project->hasMap())->toBeTrue()
        ->and($project->sector_id)->toBe($sector->id)
        ->and(makeProject(['en' => ['title' => 'Sample other']], ['sector_id' => $postCategory->id])->sector_id)->toBeNull();

    expect(fn () => makeProject(['en' => ['title' => 'Sample']], ['starts_on' => '2026-05-01', 'ends_on' => '2026-04-01']))->toThrow(ValidationException::class)
        ->and(fn () => makeProject(['en' => ['title' => 'Sample']], ['progress' => 120]))->toThrow(ValidationException::class)
        ->and(fn () => makeProject(['en' => ['title' => 'Sample']], ['budget' => -5]))->toThrow(ValidationException::class);
});

it('saves statistics with a label, unit and source per language and shows the number plainly', function () {
    $statistic = makeStatistic([
        'en' => ['label' => 'Sample trees planted', 'unit' => 'million', 'source' => 'Sample bureau'],
        'am' => ['label' => 'የተተከሉ ችግኞች ናሙና'],
    ], '12500000', ['as_of' => '2026-09-30', 'source_url' => 'https://example.org/report']);

    expect($statistic->formatted())->toBe('12,500,000')
        ->and(makeStatistic(['en' => ['label' => 'Sample share']], 12.5)->formatted())->toBe('12.5')
        ->and($statistic->translations()->count())->toBe(2);

    app(SaveStatistic::class)->handle($statistic, ['value' => 3, 'translations' => ['en' => ['label' => 'Sample trees planted'], 'am' => ['label' => '']]], staff(SystemRole::Publisher));
    expect($statistic->refresh()->translations()->count())->toBe(1);

    expect(fn () => makeStatistic(['en' => ['label' => 'Sample']], 'many'))->toThrow(ValidationException::class)
        ->and(fn () => makeStatistic(['en' => ['label' => 'Sample']], 1, ['source_url' => 'http://example.org']))->toThrow(ValidationException::class)
        ->and(fn () => makeStatistic(['en' => ['label' => '']], 1))->toThrow(ValidationException::class);
});

it('lists published projects with filters by sector, status and office', function () {
    $water = projectSector('Sample water');
    $roads = projectSector('Sample roads');
    makeProject(['en' => ['title' => 'Sample borehole', 'published' => true]], ['sector_id' => $water->id, 'status' => 'ongoing', 'progress' => 60]);
    makeProject(['en' => ['title' => 'Sample asphalt road', 'published' => true]], ['sector_id' => $roads->id, 'status' => 'completed']);
    makeProject(['en' => ['title' => 'Sample draft clinic']]);

    $this->get('/en/projects')->assertOk()
        ->assertSee('Sample borehole')->assertSee('Sample asphalt road')->assertDontSee('Sample draft clinic')
        ->assertSee('Sample water')->assertSee('aria-valuenow="60"', escape: false);
    $this->get('/en/projects?sector='.$water->id)->assertSee('Sample borehole')->assertDontSee('Sample asphalt road');
    $this->get('/en/projects?status=completed')->assertSee('Sample asphalt road')->assertDontSee('Sample borehole');
    $this->get('/en/projects?status=unknown')->assertSee('Sample borehole');
});

it('shows a project with its progress, numbers, photos, map and the budget only when public', function () {
    $photo = app(UploadMedia::class)->handle(TestFiles::image(400, 300), 'site.jpg');
    $photo->forceFill(['alt' => ['en' => 'Sample site']])->save();
    $album = app(SaveGallery::class)->handle(new Gallery, [
        'translations' => ['en' => ['title' => 'Sample site visit', 'published' => true]],
        'items' => [['media_id' => $photo->id]],
    ], staff(SystemRole::Publisher));
    $project = makeProject(['en' => ['title' => 'Sample school building', 'summary' => 'Sample summary', 'published' => true]], [
        'status' => 'ongoing', 'progress' => 75, 'budget' => 2500000, 'budget_public' => true, 'gallery_id' => $album->id,
        'latitude' => '9.31', 'longitude' => '42.12', 'starts_on' => '2026-01-15',
    ]);
    makeStatistic(['en' => ['label' => 'Sample classrooms', 'unit' => 'rooms']], 24, ['project_id' => $project->id]);

    $this->get('/en/projects/sample-school-building')->assertOk()
        ->assertSee('Sample summary')
        ->assertSee('aria-valuenow="75"', escape: false)
        ->assertSee('Sample classrooms')->assertSee('24')
        ->assertSee(__('projects.birr', ['amount' => '2,500,000'], 'en'))
        ->assertSee(route('gallery.show', ['locale' => 'en', 'slug' => 'sample-site-visit']), escape: false)
        ->assertSee('openstreetmap.org/export/embed.html', escape: false)
        ->assertSee('"@type":"Project"', escape: false);

    app(SaveProject::class)->handle($project, ['status' => 'ongoing', 'budget' => 2500000, 'budget_public' => false, 'translations' => ['en' => ['title' => 'Sample school building', 'published' => true]]], staff(SystemRole::Publisher));
    $this->get('/en/projects/sample-school-building')->assertDontSee('2,500,000');
});

it('shows achievements by sector with counts, numbers and the regional figures', function () {
    $health = projectSector('Sample health');
    makeProject(['en' => ['title' => 'Sample health centre', 'published' => true]], ['sector_id' => $health->id, 'status' => 'completed', 'ends_on' => '2026-02-01']);
    makeProject(['en' => ['title' => 'Sample clinic upgrade', 'published' => true]], ['sector_id' => $health->id, 'status' => 'ongoing']);
    makeStatistic(['en' => ['label' => 'Sample health posts']], 38, ['sector_id' => $health->id]);
    makeStatistic(['en' => ['label' => 'Sample regional members']], 120000);
    makeStatistic(['en' => ['label' => 'Sample hidden number']], 5, ['on_achievements' => false]);

    $this->get('/en/achievements')->assertOk()
        ->assertSee('Sample health')
        ->assertSee('Sample health posts')->assertSee('38')
        ->assertSee(__('projects.regional', [], 'en'))->assertSee('Sample regional members')->assertSee('120,000')
        ->assertDontSee('Sample hidden number')
        ->assertSee('Sample health centre')
        ->assertSee(trans_choice('projects.chart_title', 2, ['count' => 2], 'en'))
        ->assertSee(route('projects.index', ['locale' => 'en', 'sector' => $health->id]), escape: false);
});

it('puts statistics into pages and posts as a block', function () {
    $statistic = makeStatistic(['en' => ['label' => 'Sample kebeles reached'], 'am' => ['label' => 'የደረሱ ቀበሌዎች ናሙና']], 36);
    $body = ['type' => 'doc', 'content' => [['type' => 'customBlock', 'attrs' => ['id' => 'statistics', 'config' => ['statistic_ids' => [(string) $statistic->id, 'x']]]]]];

    app()->setLocale('en');
    expect(BodyRenderer::toHtml($body))->toContain('Sample kebeles reached')->toContain('36');
    app()->setLocale('am');
    expect(BodyRenderer::toHtml($body))->toContain('የደረሱ ቀበሌዎች ናሙና');
});

it('shows projects on the homepage, in search and the sitemaps, and Amharic projects to Harari readers', function () {
    makeProject(['am' => ['title' => 'የናሙና መንገድ ፕሮጀክት', 'published' => true]]);
    makeProject(['en' => ['title' => 'Sample irrigation canal', 'published' => true]], ['featured' => true]);
    HomepageSection::query()->where('key', SectionKey::Projects->value)->update(['enabled' => true]);

    $this->get('/har/projects')->assertOk()->assertSee('የናሙና መንገድ ፕሮጀክት')->assertSee('lang="am"', escape: false);
    $this->get('/en')->assertSee('id="home-projects"', escape: false)->assertSee('Sample irrigation canal');
    $this->get('/en/search?q=irrigation')->assertSee(__('search.kind.projects', [], 'en'))->assertSee('Sample irrigation canal');
    $this->get('/sitemaps/en.xml')
        ->assertSee(url('/en/projects/sample-irrigation-canal'), escape: false)
        ->assertSee(url('/en/achievements'), escape: false);
});

it('lets those who manage projects add projects and statistics in the admin', function () {
    $this->actingAs(staff(SystemRole::Author))->get('/admin/projects')->assertForbidden();
    $this->actingAs(staff(SystemRole::Author))->get('/admin/statistics')->assertForbidden();

    $sector = projectSector('Sample energy');
    $this->actingAs(staff(SystemRole::Editor));
    livewire(CreateProject::class)
        ->fillForm([
            'status' => 'ongoing',
            'progress' => 30,
            'sector_id' => $sector->id,
            'translations' => ['om' => ['title' => 'Pirojektii fakkeenyaa', 'location' => 'Aanaa fakkeenyaa', 'published' => true]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $project = Project::query()->firstOrFail();
    expect($project->translation('om', fallback: false)?->getAttribute('location'))->toBe('Aanaa fakkeenyaa');

    livewire(CreateStatistic::class)
        ->fillForm([
            'value' => 420,
            'project_id' => $project->id,
            'translations' => ['om' => ['label' => 'Manneen ijaaraman', 'unit' => 'mana']],
        ])
        ->call('create')
        ->assertHasNoFormErrors();
    expect(Statistic::query()->firstOrFail()->project_id)->toBe($project->id);
});
