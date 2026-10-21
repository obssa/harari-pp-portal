<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Api\Support\ApiPresenter;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTranslation;
use App\Domain\Organisation\Queries\PublicDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public documents and notices (scope `documents`); members-only documents
 * never appear. Filters: `type`, `year`, `office`, and `open=1` for
 * vacancies, tenders and notices still open.
 */
final class DocumentController extends ApiController
{
    public function __construct(private readonly ApiPresenter $present) {}

    public function index(Request $request): JsonResponse
    {
        $locale = $this->locale();
        $type = DocumentType::tryFrom((string) $this->filter($request, 'type'));
        $year = (int) $this->filter($request, 'year');
        $office = $this->filter($request, 'office');

        $page = PublicDirectory::visible(Document::query(), $locale)
            ->publicAccess()
            ->with(['translations.file', 'office.translations'])
            ->when($type, fn (Builder $q, DocumentType $t) => $q->where('type', $t->value))
            ->when($year > 0, fn (Builder $q) => $q->where('year', $year))
            ->when($office, fn (Builder $q, string $id) => $q->where('office_id', $id))
            ->when($request->boolean('open'), fn (Builder $q) => $q->ofTypes(DocumentType::notices())->open())
            ->orderByDesc('issued_on')->orderByDesc('created_at')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->page($page, fn (Document $document): ?array => ($shown = PublicDirectory::shown($document, $locale)) instanceof DocumentTranslation
            ? $this->present->document($document, $shown)
            : null);
    }

    public function show(string $key): JsonResponse
    {
        [$document, $shown] = $this->find(Document::query()->publicAccess()->with('office.translations'), $key);
        abort_unless($shown instanceof DocumentTranslation, 404);
        $document->load('translations.file');

        return $this->one($this->present->document($document, $shown, full: true));
    }
}
