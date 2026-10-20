<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Documents\Actions\SaveDocument;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Models\Document;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Models\Media;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\RelationManagers\VersionsRelationManager;
use Carbon\CarbonImmutable;
use Filament\Actions\Testing\TestAction;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestFiles;

use function Pest\Livewire\livewire;

function documentFile(string $name = 'sample.pdf'): Media
{
    return app(UploadMedia::class)->handle(TestFiles::pdf($name), $name);
}

/**
 * @param  array<string, array<string, mixed>>  $translations
 * @param  array<string, mixed>  $data
 */
function makeDocument(array $translations, DocumentType $type = DocumentType::Report, array $data = []): Document
{
    return app(SaveDocument::class)->handle(new Document, ['type' => $type->value, 'translations' => $translations, ...$data], staff(SystemRole::Publisher));
}

it('keeps a file per language and the replaced files as earlier versions', function () {
    $first = documentFile('report-v1.pdf');
    $second = documentFile('report-v2.pdf');

    $document = makeDocument(['en' => ['title' => 'Sample annual report', 'file_id' => $first->id, 'published' => true]], data: ['year' => 2025]);
    $translation = $document->translation('en');
    expect($translation?->getAttribute('file_id'))->toBe($first->id)->and($translation?->getAttribute('version'))->toBe(1);

    app(SaveDocument::class)->handle($document, ['type' => 'report', 'year' => 2025, 'translations' => ['en' => [
        'title' => 'Sample annual report', 'file_id' => $second->id, 'version_note' => 'Corrected figures', 'published' => true,
    ]]], staff(SystemRole::Publisher));

    $document->refresh();
    expect($document->translation('en')?->getAttribute('version'))->toBe(2)
        ->and($document->translation('en')?->getAttribute('version_note'))->toBe('Corrected figures')
        ->and($document->versions()->count())->toBe(1)
        ->and($document->versions()->first()?->media_id)->toBe($first->id)
        ->and($first->refresh()->isUsed())->toBeTrue();

    app(SaveDocument::class)->restore($document, (int) $document->versions()->first()?->id, staff(SystemRole::Publisher));
    $document->refresh();
    expect($document->translation('en')?->getAttribute('file_id'))->toBe($first->id)
        ->and($document->translation('en')?->getAttribute('version'))->toBe(3)
        ->and($document->versions()->pluck('version')->all())->toBe([2, 1]);
});

it('checks the type, the year and that files are documents', function (array $data) {
    expect(fn () => app(SaveDocument::class)->handle(new Document, $data, staff(SystemRole::Publisher)))->toThrow(ValidationException::class);
})->with([
    'no type' => [['translations' => ['en' => ['title' => 'Sample']]]],
    'odd year' => [['type' => 'report', 'year' => 1800, 'translations' => ['en' => ['title' => 'Sample']]]],
    'no title' => [['type' => 'report', 'translations' => ['en' => ['title' => '']]]],
    'picture as file' => [fn () => ['type' => 'report', 'translations' => ['en' => ['title' => 'Sample', 'file_id' => app(UploadMedia::class)->handle(TestFiles::image(40, 40), 'x.jpg')->id]]]],
]);

it('keeps deadlines only for vacancies, tenders and notices', function () {
    $deadline = CarbonImmutable::now()->addWeek()->toDateTimeString();

    expect(makeDocument(['en' => ['title' => 'Sample report']], data: ['deadline_at' => $deadline])->deadline_at)->toBeNull()
        ->and(makeDocument(['en' => ['title' => 'Sample vacancy']], DocumentType::Vacancy, ['deadline_at' => $deadline])->deadline_at)->not->toBeNull();
});

it('lists library documents with filters and keeps notices on their own page', function () {
    $office = app(SaveOffice::class)->handle(new Office, ['type' => 'sector', 'translations' => ['en' => ['name' => 'Sample sector office', 'published' => true]]], staff(SystemRole::Publisher));
    makeDocument(['en' => ['title' => 'Sample budget report', 'published' => true]], data: ['year' => 2025, 'office_id' => $office->id]);
    makeDocument(['en' => ['title' => 'Sample land policy', 'published' => true]], DocumentType::Policy, ['year' => 2024]);
    makeDocument(['en' => ['title' => 'Sample draft bylaw']], DocumentType::Bylaw);
    makeDocument(['en' => ['title' => 'Sample driver vacancy', 'published' => true]], DocumentType::Vacancy);

    $this->get('/en/documents')->assertOk()
        ->assertSee('Sample budget report')->assertSee('Sample land policy')
        ->assertDontSee('Sample draft bylaw')->assertDontSee('Sample driver vacancy')
        ->assertSee('<option value="2025"', escape: false)
        ->assertSee('Sample sector office');
    $this->get('/en/documents?type=policy')->assertSee('Sample land policy')->assertDontSee('Sample budget report');
    $this->get('/en/documents?year=2025')->assertSee('Sample budget report')->assertDontSee('Sample land policy');
    $this->get('/en/documents?office='.$office->id)->assertSee('Sample budget report')->assertDontSee('Sample land policy');
    $this->get('/en/documents?type=vacancy')->assertSee('Sample budget report');
});

it('shows open notices by their deadline and the recently closed ones apart', function () {
    makeDocument(['en' => ['title' => 'Sample later vacancy', 'published' => true]], DocumentType::Vacancy, ['deadline_at' => CarbonImmutable::now()->addDays(9)->toDateTimeString()]);
    makeDocument(['en' => ['title' => 'Sample sooner tender', 'published' => true]], DocumentType::Tender, ['deadline_at' => CarbonImmutable::now()->addDays(2)->toDateTimeString()]);
    makeDocument(['en' => ['title' => 'Sample standing notice', 'published' => true]], DocumentType::Notice);
    makeDocument(['en' => ['title' => 'Sample closed tender', 'published' => true]], DocumentType::Tender, ['deadline_at' => CarbonImmutable::now()->subDay()->toDateTimeString()]);

    $html = (string) $this->get('/en/notices')->assertOk()->getContent();
    $open = substr($html, (int) strpos($html, 'id="notices-open"'), (int) strpos($html, 'id="notices-closed"') - (int) strpos($html, 'id="notices-open"'));

    expect(strpos($open, 'Sample sooner tender'))->toBeLessThan(strpos($open, 'Sample later vacancy'))
        ->and(strpos($open, 'Sample later vacancy'))->toBeLessThan(strpos($open, 'Sample standing notice'))
        ->and($open)->not->toContain('Sample closed tender')
        ->and($html)->toContain('Sample closed tender');
});

it('shows a document with its viewer, version, related documents and the file of another language', function () {
    $amharic = documentFile('programme-am.pdf');
    $old = documentFile('programme-old.pdf');
    $related = makeDocument(['en' => ['title' => 'Sample related speech', 'published' => true]], DocumentType::Speech);
    $document = makeDocument([
        'am' => ['title' => 'የናሙና ፕሮግራም', 'file_id' => $old->id, 'published' => true],
        'en' => ['title' => 'Sample party programme', 'summary' => 'Sample summary', 'published' => true],
    ], DocumentType::Programme, ['related' => [$related->id], 'issued_on' => '2026-03-10']);
    app(SaveDocument::class)->handle($document, ['type' => 'programme', 'related' => [$related->id], 'translations' => [
        'am' => ['title' => 'የናሙና ፕሮግራም', 'file_id' => $amharic->id, 'version_note' => 'የተሻሻለ', 'published' => true],
        'en' => ['title' => 'Sample party programme', 'summary' => 'Sample summary', 'published' => true],
    ]], staff(SystemRole::Publisher));

    $this->get('/en/documents/sample-party-programme')->assertOk()
        ->assertSee('Sample summary')
        ->assertSee(route('media.file', ['uuid' => $amharic->uuid, 'name' => $amharic->file_name]), escape: false)
        ->assertSee(__('documents.file_in', ['language' => 'አማርኛ'], 'en'))
        ->assertSee(route('documents.download', ['locale' => 'en', 'slug' => 'sample-party-programme']), escape: false)
        ->assertSee(__('documents.version', ['number' => 2], 'en'))
        ->assertSee(__('documents.earlier_versions', [], 'en'))
        ->assertSee('Sample related speech')
        ->assertSee('"@type":"DigitalDocument"', escape: false);

    $this->get('/har/documents')->assertOk()->assertSee('የናሙና ፕሮግራም')->assertSee('lang="am"', escape: false);
});

it('counts downloads once an hour per visitor and leaves robots out', function () {
    $current = documentFile('form-v2.pdf');
    $old = documentFile('form-v1.pdf');
    $document = makeDocument(['en' => ['title' => 'Sample membership form', 'file_id' => $old->id, 'published' => true]], DocumentType::Form);
    app(SaveDocument::class)->handle($document, ['type' => 'form', 'translations' => ['en' => ['title' => 'Sample membership form', 'file_id' => $current->id, 'published' => true]]], staff(SystemRole::Publisher));

    $person = ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14) Sample'];
    $response = $this->get('/en/documents/sample-membership-form/download', $person)
        ->assertRedirect(route('media.file', ['uuid' => $current->uuid, 'name' => $current->file_name]));
    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');
    $this->get('/en/documents/sample-membership-form/download', $person);
    $this->get('/en/documents/sample-membership-form/download', ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0) Other']);
    $this->get('/en/documents/sample-membership-form/download', ['User-Agent' => 'Googlebot/2.1']);

    expect($document->refresh()->download_count)->toBe(2);

    $this->get('/en/documents/sample-membership-form/download?version=1', $person)
        ->assertRedirect(route('media.file', ['uuid' => $old->uuid, 'name' => $old->file_name]));
    $this->get('/en/documents/sample-membership-form/download?version=9', $person)->assertNotFound();
});

it('lets only this site show PDFs in a frame', function () {
    $pdf = documentFile('frame.pdf');
    $image = app(UploadMedia::class)->handle(TestFiles::image(40, 40), 'frame.jpg');

    $this->get(route('media.file', ['uuid' => $pdf->uuid, 'name' => $pdf->file_name]))->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");
    $this->get(route('media.file', ['uuid' => $image->uuid, 'name' => $image->file_name]))
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('keeps members-only documents out of every public place until the member portal exists', function () {
    $document = makeDocument(['en' => ['title' => 'Sample internal guideline', 'published' => true]], DocumentType::Policy);
    $document->forceFill(['access' => Document::ACCESS_MEMBERS])->save();

    $this->get('/en/documents/sample-internal-guideline')->assertNotFound();
    $this->get('/en/documents')->assertDontSee('Sample internal guideline');
    $this->get('/en/search?q=guideline')->assertDontSee('Sample internal guideline');
    $this->get('/sitemaps/en.xml')->assertDontSee('sample-internal-guideline');
});

it('finds documents in search and lists them in the sitemaps', function () {
    makeDocument(['en' => ['title' => 'Sample irrigation strategy', 'published' => true]], DocumentType::Policy);

    $this->get('/en/search?q=irrigation')->assertSee('Sample irrigation strategy')->assertSee(DocumentType::Policy->label());
    $this->get('/sitemaps/en.xml')
        ->assertSee(url('/en/documents/sample-irrigation-strategy'), escape: false)
        ->assertSee(url('/en/notices'), escape: false);
});

it('lets those who manage documents add them and bring back earlier versions in the admin', function () {
    $this->actingAs(staff(SystemRole::Author))->get('/admin/documents')->assertForbidden();
    $this->actingAs(staff(SystemRole::OfficeHead))->get('/admin/documents')->assertOk();

    $first = documentFile('bylaw-v1.pdf');
    $second = documentFile('bylaw-v2.pdf');
    $this->actingAs(staff(SystemRole::Editor));
    livewire(CreateDocument::class)
        ->fillForm([
            'type' => 'bylaw',
            'year' => 2026,
            'translations' => ['om' => ['title' => 'Dambii fakkeenyaa', 'file_id' => $first->id, 'published' => true]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $document = Document::query()->firstOrFail();
    livewire(EditDocument::class, ['record' => $document->getRouteKey()])
        ->assertFormSet(['translations.om.file_id' => $first->id])
        ->fillForm(['translations.om.file_id' => $second->id, 'translations.om.version_note' => 'Fooyya\'ina'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($document->refresh()->translation('om', fallback: false)?->getAttribute('file_id'))->toBe($second->id);

    $version = $document->versions()->firstOrFail();
    livewire(VersionsRelationManager::class, ['ownerRecord' => $document, 'pageClass' => EditDocument::class])
        ->assertCanSeeTableRecords([$version])
        ->callAction(TestAction::make('restore')->table($version));
    expect($document->refresh()->translation('om', fallback: false)?->getAttribute('file_id'))->toBe($first->id);
});
