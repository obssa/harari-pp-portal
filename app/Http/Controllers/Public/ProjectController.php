<?php

namespace App\Http\Controllers\Public;

use App\Domain\Content\Models\Category;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Organisation\Models\Office;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectTranslation;
use App\Domain\Projects\Models\Statistic;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Projects and achievements (PLAN.md §4 Programmes & Achievements,
 * ADR-0027): the project list with filters, each project's page, and the
 * achievements page by sector with its numbers and simple charts.
 */
final class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $locale = app()->getLocale();
        $status = ProjectStatus::tryFrom((string) $request->query('status', ''));
        $sectorId = (string) $request->query('sector', '');
        $officeId = (string) $request->query('office', '');

        $base = fn (): Builder => PublicDirectory::visible(Project::query(), $locale);

        $projects = $base()
            ->with(['cover', 'sector.translations', 'office.translations'])
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status?->value))
            ->when($sectorId !== '', fn (Builder $q) => $q->where('sector_id', $sectorId))
            ->when($officeId !== '', fn (Builder $q) => $q->where('office_id', $officeId))
            ->orderByDesc('featured')->orderByDesc('starts_on')->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        return view('pages.projects.index', [
            'projects' => $projects,
            'statuses' => ProjectStatus::options(),
            'sectors' => Category::query()->whereIn('id', $base()->whereNotNull('sector_id')->select('sector_id'))->with('translations')->get()
                ->mapWithKeys(fn (Category $c): array => [$c->id => $c->name($locale)])->sort()->all(),
            'offices' => Office::query()->whereIn('id', $base()->whereNotNull('office_id')->select('office_id'))->with('translations')->get()
                ->mapWithKeys(fn (Office $o): array => [$o->id => $o->name($locale)])->sort()->all(),
            'filters' => ['status' => $status?->value, 'sector' => $sectorId ?: null, 'office' => $officeId ?: null],
        ]);
    }

    public function show(string $slug): View
    {
        $locale = app()->getLocale();
        /** @var ProjectTranslation|null $translation */
        $translation = ProjectTranslation::query()->where('locale', $locale)->where('slug', $slug)->first();
        $project = $translation?->project()->with(['translations', 'cover', 'sector.translations', 'office.translations', 'gallery.items.media', 'statistics.translations'])->first();
        abort_if($translation === null || $project === null || ! $translation->isLive(), 404);

        $gallery = $project->gallery !== null ? PublicDirectory::shown($project->gallery, $locale) : null;
        $url = route('projects.show', ['slug' => $translation->slug]);

        return view('pages.projects.show', [
            'project' => $project,
            'translation' => $translation,
            'body' => BodyRenderer::toHtml($translation->body),
            'office' => $project->office !== null ? PublicDirectory::shown($project->office, $locale) : null,
            'galleryTranslation' => $gallery,
            'photos' => $gallery !== null ? $project->gallery->items->take(12)->values() : collect(),
            'alternates' => PublicDirectory::alternates($project, 'projects.show', 'projects.index'),
            'image' => PublicDirectory::shareImage($project->cover, 'landscape', "project-{$project->id}-{$locale}", $translation->title, __('projects.title')),
            'jsonLd' => [array_filter([
                '@type' => 'Project',
                '@id' => $url.'#project',
                'name' => $translation->title,
                'description' => $translation->summary,
                'url' => $url,
                'foundingDate' => $project->starts_on?->toDateString(),
                'parentOrganization' => ['@id' => url('/').'#organization'],
            ], fn (mixed $value): bool => $value !== null)],
        ]);
    }

    public function achievements(): View
    {
        $locale = app()->getLocale();
        $projects = PublicDirectory::visible(Project::query(), $locale)->with(['translations', 'cover', 'sector.translations'])->get();
        $statistics = Statistic::query()->where('on_achievements', true)->with(['translations', 'sector.translations'])
            ->orderBy('sort')->orderBy('id')->get();

        $sectorIds = $projects->pluck('sector_id')->merge($statistics->pluck('sector_id'))->filter()->unique()->all();
        $sectors = Category::query()->whereKey($sectorIds)->where('type', Category::TYPE_SECTOR)->with('translations')->orderBy('sort')->get()
            ->map(function (Category $sector) use ($projects, $statistics, $locale): array {
                $own = $projects->where('sector_id', $sector->id);

                return [
                    'sector' => $sector,
                    'name' => $sector->name($locale),
                    'statistics' => $statistics->where('sector_id', $sector->id)->values(),
                    'counts' => self::counts($own),
                    'total' => $own->count(),
                    'completed' => $own->where('status', ProjectStatus::Completed)->sortByDesc('ends_on')->take(3)->values(),
                ];
            });

        return view('pages.projects.achievements', [
            'regional' => $statistics->whereNull('sector_id')->values(),
            'counts' => self::counts($projects),
            'total' => $projects->count(),
            'sectors' => $sectors,
        ]);
    }

    /**
     * Projects per status, in the order of the statuses.
     *
     * @param  iterable<Project>  $projects
     * @return array<string, int>
     */
    private static function counts(iterable $projects): array
    {
        $counts = array_fill_keys(array_map(fn (ProjectStatus $s): string => $s->value, ProjectStatus::cases()), 0);
        foreach ($projects as $project) {
            $counts[$project->status->value]++;
        }

        return $counts;
    }
}
