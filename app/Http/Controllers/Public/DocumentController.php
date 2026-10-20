<?php

namespace App\Http\Controllers\Public;

use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTranslation;
use App\Domain\Organisation\Models\Office;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The documents library and the notices page (PLAN.md §4 Resources and Get
 * involved, ADR-0026). Downloads go through here so they can be counted.
 */
final class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $locale = app()->getLocale();
        $type = DocumentType::tryFrom((string) $request->query('type', ''));
        $type = $type !== null && ! $type->isNotice() ? $type : null;
        $year = (int) $request->query('year', 0);
        $officeId = (string) $request->query('office', '');

        $base = fn (): Builder => PublicDirectory::visible(Document::query(), $locale)->publicAccess()->ofTypes(DocumentType::library());

        $documents = $base()
            ->with(['translations.file', 'office.translations'])
            ->when($type !== null, fn (Builder $q) => $q->where('type', $type?->value))
            ->when($year > 0, fn (Builder $q) => $q->where('year', $year))
            ->when($officeId !== '', fn (Builder $q) => $q->where('office_id', $officeId))
            ->orderByDesc('issued_on')->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $offices = Office::query()->whereIn('id', $base()->whereNotNull('office_id')->select('office_id'))->with('translations')->get()
            ->mapWithKeys(fn (Office $office): array => [$office->id => $office->name($locale)])->sort()->all();

        return view('pages.documents.index', [
            'documents' => $documents,
            'types' => DocumentType::options(DocumentType::library()),
            'years' => $base()->whereNotNull('year')->distinct()->orderByDesc('year')->pluck('year')->all(),
            'offices' => $offices,
            'filters' => ['type' => $type?->value, 'year' => $year ?: null, 'office' => $officeId ?: null],
        ]);
    }

    public function notices(): View
    {
        $locale = app()->getLocale();
        $base = fn (): Builder => PublicDirectory::visible(Document::query(), $locale)->publicAccess()->ofTypes(DocumentType::notices())
            ->with(['translations.file', 'office.translations']);

        return view('pages.documents.notices', [
            // Open ones by the nearest deadline; those without a deadline after them.
            'open' => $base()->open()->orderByRaw('deadline_at is null')->orderBy('deadline_at')->orderByDesc('created_at')->limit(50)->get(),
            'closed' => $base()->closed()->orderByDesc('deadline_at')->limit(20)->get(),
        ]);
    }

    public function show(string $slug): View
    {
        $locale = app()->getLocale();
        [$document, $translation] = $this->find($slug);

        $file = $document->fileFor($locale);
        $related = $document->related()->publicAccess()->with('translations')->get()
            ->map(fn (Document $other): ?array => ($shown = PublicDirectory::shown($other, $locale)) instanceof DocumentTranslation ? ['document' => $other, 'translation' => $shown] : null)
            ->filter()->values();
        $versions = $document->versions()->where('locale', $file->locale ?? $locale)->with('media')->get()
            ->filter(fn ($version): bool => $version->media !== null)->values();
        $index = $document->type->isNotice() ? 'documents.notices' : 'documents.index';

        return view('pages.documents.show', [
            'document' => $document,
            'translation' => $translation,
            'body' => BodyRenderer::toHtml($translation->body),
            'file' => $file,
            'versions' => $versions,
            'related' => $related,
            'office' => $document->office !== null ? PublicDirectory::shown($document->office, $locale) : null,
            'league' => $document->league !== null ? PublicDirectory::shown($document->league, $locale) : null,
            'indexRoute' => $index,
            'alternates' => PublicDirectory::alternates($document, 'documents.show', $index),
            'jsonLd' => [array_filter([
                '@type' => 'DigitalDocument',
                'name' => $translation->title,
                'description' => $translation->summary,
                'inLanguage' => $file->locale ?? $locale,
                'dateCreated' => $document->issued_on?->toDateString(),
                'encodingFormat' => $file?->file?->mime_type,
                'url' => route('documents.show', ['slug' => $translation->slug]),
                'publisher' => ['@id' => url('/').'#organization'],
            ], fn (mixed $value): bool => $value !== null)],
        ]);
    }

    /**
     * Counts the download (once an hour per visitor) and hands over the file.
     */
    public function download(Request $request, string $slug): RedirectResponse
    {
        [$document] = $this->find($slug);
        $locale = app()->getLocale();

        $media = null;
        $version = (int) $request->query('version', 0);
        if ($version > 0) {
            $file = $document->fileFor($locale);
            $media = $document->versions()->where('locale', $file->locale ?? $locale)->where('version', $version)->first()?->media;
        } else {
            $media = $document->fileFor($locale)?->file;
        }
        abort_if($media === null, 404);

        $visitor = hash('sha256', $document->id.'|'.$request->ip().'|'.(string) $request->userAgent());
        if (! self::looksLikeRobot((string) $request->userAgent()) && Cache::add("document-download:{$visitor}", true, 3600)) {
            $document->newQuery()->whereKey($document->id)->increment('download_count');
        }

        return redirect()->to(route('media.file', ['uuid' => $media->uuid, 'name' => $media->file_name]))
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex');
    }

    public static function looksLikeRobot(string $agent): bool
    {
        return $agent === '' || preg_match('/bot|crawl|spider|slurp|preview|monitor|curl|wget|python|headless|lighthouse/i', $agent) === 1;
    }

    /**
     * @return array{0: Document, 1: DocumentTranslation}
     */
    private function find(string $slug): array
    {
        /** @var DocumentTranslation|null $translation */
        $translation = DocumentTranslation::query()->where('locale', app()->getLocale())->where('slug', $slug)->first();
        $document = $translation?->document()->with(['translations.file', 'office.translations', 'league.translations'])->first();
        abort_if($translation === null || $document === null || ! $translation->isLive() || $document->access !== Document::ACCESS_PUBLIC, 404);

        return [$document, $translation];
    }
}
