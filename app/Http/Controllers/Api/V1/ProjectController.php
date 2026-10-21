<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Api\Support\ApiPresenter;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Domain\Projects\Enums\ProjectStatus;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Development projects (scope `projects`), filtered by `status`, `sector`
 * and `office`. Budgets show only where the project's budget is public.
 */
final class ProjectController extends ApiController
{
    public function __construct(private readonly ApiPresenter $present) {}

    public function index(Request $request): JsonResponse
    {
        $locale = $this->locale();
        $status = ProjectStatus::tryFrom((string) $this->filter($request, 'status'));

        $page = PublicDirectory::visible(Project::query(), $locale)
            ->with(['cover', 'sector.translations'])
            ->when($status, fn (Builder $q, ProjectStatus $s) => $q->where('status', $s->value))
            ->when($this->filter($request, 'sector'), fn (Builder $q, string $id) => $q->where('sector_id', $id))
            ->when($this->filter($request, 'office'), fn (Builder $q, string $id) => $q->where('office_id', $id))
            ->orderByDesc('featured')->orderByDesc('starts_on')->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->page($page, fn (Project $project): ?array => ($shown = PublicDirectory::shown($project, $locale)) instanceof ProjectTranslation
            ? $this->present->project($project, $shown)
            : null);
    }

    public function show(string $key): JsonResponse
    {
        [$project, $shown] = $this->find(Project::query()->with(['cover', 'sector.translations']), $key);
        abort_unless($shown instanceof ProjectTranslation, 404);

        return $this->one($this->present->project($project, $shown, full: true));
    }
}
