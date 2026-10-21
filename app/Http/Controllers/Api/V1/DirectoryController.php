<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Api\Support\ApiPresenter;
use App\Domain\Organisation\Enums\LeaderBody;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\LeaderTranslation;
use App\Domain\Organisation\Models\Office;
use App\Domain\Organisation\Models\OfficeTranslation;
use App\Domain\Organisation\Queries\PublicDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Offices and leaders (scope `directory`). Offices filter by `type` and
 * `parent`; leaders by `body` and `office`.
 */
final class DirectoryController extends ApiController
{
    public function __construct(private readonly ApiPresenter $present) {}

    public function offices(Request $request): JsonResponse
    {
        $locale = $this->locale();
        $type = OfficeType::tryFrom((string) $this->filter($request, 'type'));

        $page = PublicDirectory::visible(Office::query(), $locale)
            ->with('coverImage')
            ->when($type, fn (Builder $q, OfficeType $t) => $q->where('type', $t->value))
            ->when($this->filter($request, 'parent'), fn (Builder $q, string $id) => $q->where('parent_id', $id))
            ->orderBy('sort')->orderBy('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->page($page, fn (Office $office): ?array => ($shown = PublicDirectory::shown($office, $locale)) instanceof OfficeTranslation
            ? $this->present->officeRecord($office, $shown)
            : null);
    }

    public function office(string $key): JsonResponse
    {
        [$office, $shown] = $this->find(Office::query()->with('coverImage'), $key);
        abort_unless($shown instanceof OfficeTranslation, 404);

        return $this->one($this->present->officeRecord($office, $shown, full: true));
    }

    public function leaders(Request $request): JsonResponse
    {
        $locale = $this->locale();
        $body = LeaderBody::tryFrom((string) $this->filter($request, 'body'));

        $page = PublicDirectory::visible(Leader::query(), $locale)
            ->with('portrait')
            ->when($body, fn (Builder $q, LeaderBody $b) => $q->where('body', $b->value))
            ->when($this->filter($request, 'office'), fn (Builder $q, string $id) => $q->where('office_id', $id))
            ->orderBy('rank')->orderBy('id')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->page($page, fn (Leader $leader): ?array => ($shown = PublicDirectory::shown($leader, $locale)) instanceof LeaderTranslation
            ? $this->present->leader($leader, $shown)
            : null);
    }

    public function leader(string $key): JsonResponse
    {
        [$leader, $shown] = $this->find(Leader::query()->with('portrait'), $key);
        abort_unless($shown instanceof LeaderTranslation, 404);

        return $this->one($this->present->leader($leader, $shown, full: true));
    }
}
