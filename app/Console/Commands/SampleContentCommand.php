<?php

namespace App\Console\Commands;

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Models\Category;
use App\Domain\Documents\Actions\SaveDocument;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Models\DocumentTranslation;
use App\Domain\Events\Actions\SaveEvent;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventTranslation;
use App\Domain\Forms\Actions\SaveForm;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Models\FormTranslation;
use App\Domain\Forms\Support\FormField;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Models\Media;
use App\Domain\Projects\Actions\SaveProject;
use App\Domain\Projects\Actions\SaveStatistic;
use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Models\ProjectTranslation;
use App\Domain\Projects\Models\Statistic;
use App\Domain\Projects\Models\StatisticTranslation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Clearly labelled sample events, documents, projects, statistics and a
 * feedback form (Phase 3 gate), so every new page can be checked in every
 * language on staging. Every title starts with "Sample", "ናሙና" or
 * "Fakkeenya"; --remove deletes exactly these. Never for production.
 */
final class SampleContentCommand extends Command
{
    /** Marks sample records in every language. */
    public const LABEL = ['en' => 'Sample: ', 'am' => 'ናሙና፦ ', 'om' => 'Fakkeenya: '];

    protected $signature = 'portal:sample-content {--remove : Delete the sample records again}';

    protected $description = 'Add (or remove) clearly labelled sample events, documents, projects and statistics';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->components->error('Sample content is not for production.');

            return self::FAILURE;
        }

        if ($this->option('remove')) {
            $this->components->info('Removed: '.$this->remove());

            return self::SUCCESS;
        }

        $actor = User::role(SystemRole::SuperAdmin->value)->first();
        if (! $actor instanceof User) {
            $this->components->error('A Super Admin account is needed.');

            return self::FAILURE;
        }

        $this->remove();
        DB::transaction(function () use ($actor): void {
            $this->events($actor);
            $this->documents($actor);
            $this->projects($actor);
            $this->form($actor);
        });
        $this->components->info('Sample events, documents, projects, statistics and a feedback form are in place.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $titles  without the label
     * @param  array<string, array<string, mixed>>  $more
     * @return array<string, array<string, mixed>>
     */
    private static function translations(array $titles, array $more = [], string $field = 'title'): array
    {
        $out = [];
        foreach ($titles as $locale => $title) {
            $out[$locale] = [$field => self::LABEL[$locale].$title, 'published' => true, ...($more[$locale] ?? [])];
        }

        return $out;
    }

    private function events(User $actor): void
    {
        $next = CarbonImmutable::now('Africa/Addis_Ababa')->addDays(10)->setTime(9, 0)->utc();
        app(SaveEvent::class)->handle(new Event, [
            'starts_at' => $next->toDateTimeString(),
            'ends_at' => $next->addHours(3)->toDateTimeString(),
            'latitude' => 9.3126, 'longitude' => 42.1227,
            'registration_enabled' => true, 'capacity' => 50, 'waitlist' => true,
            'translations' => self::translations(
                ['en' => 'Community consultation meeting', 'am' => 'የማኅበረሰብ ምክክር መድረክ', 'om' => 'Walgahii mari\'ii hawaasaa'],
                [
                    'en' => ['summary' => 'A sample event to test the events pages. It is not a real meeting.', 'venue' => 'Sample hall',
                        'agenda' => [['time' => '09:00', 'item' => 'Opening'], ['time' => '09:30', 'item' => 'Questions and answers']]],
                    'am' => ['summary' => 'የሁነቶች ገጾችን ለመሞከር የተዘጋጀ ናሙና ሁነት ነው። እውነተኛ ስብሰባ አይደለም።', 'venue' => 'ናሙና አዳራሽ'],
                    'om' => ['summary' => "Sagantaa fakkeenyaa fuulota sagantaalee qorachuuf qophaa'e. Walgahii dhugaa miti.", 'venue' => 'Galma fakkeenyaa'],
                ],
            ),
        ], $actor);

        app(SaveEvent::class)->handle(new Event, [
            'starts_at' => CarbonImmutable::now('UTC')->subDays(20)->toDateTimeString(),
            'translations' => self::translations(['en' => 'Past forum', 'am' => 'ያለፈ መድረክ', 'om' => 'Waltajjii darbe']),
        ], $actor);
    }

    private function documents(User $actor): void
    {
        $file = $this->pdf('sample-programme.pdf', 'Sample document');
        app(SaveDocument::class)->handle(new Document, [
            'type' => 'programme', 'year' => (int) date('Y'),
            'translations' => self::translations(
                ['en' => 'Party programme', 'am' => 'የፓርቲ ፕሮግራም', 'om' => 'Sagantaa paartii'],
                ['en' => ['file_id' => $file->id, 'summary' => 'A sample document to test the library. It is not an official document.']],
            ),
        ], $actor);
        app(SaveDocument::class)->handle(new Document, [
            'type' => 'report', 'year' => (int) date('Y') - 1,
            'translations' => self::translations(['en' => 'Annual report', 'am' => 'ዓመታዊ ሪፖርት', 'om' => 'Gabaasa waggaa']),
        ], $actor);
        app(SaveDocument::class)->handle(new Document, [
            'type' => 'vacancy', 'deadline_at' => CarbonImmutable::now('UTC')->addDays(14)->toDateTimeString(),
            'translations' => self::translations(['en' => 'Vacancy: office assistant', 'am' => 'ክፍት የሥራ ቦታ፦ የቢሮ ረዳት', 'om' => 'Bakka hojii: gargaaraa waajjiraa']),
        ], $actor);
    }

    private function projects(User $actor): void
    {
        $sector = Category::query()->create(['type' => Category::TYPE_SECTOR]);
        foreach (self::translations(['en' => 'Water', 'am' => 'ውሃ', 'om' => 'Bishaan'], [], 'name') as $locale => $values) {
            $sector->putTranslation($locale, ['name' => $values['name']]);
        }

        $water = app(SaveProject::class)->handle(new Project, [
            'sector_id' => $sector->id, 'status' => 'ongoing', 'progress' => 60, 'featured' => true,
            'starts_on' => CarbonImmutable::now()->subMonths(5)->toDateString(),
            'latitude' => 9.31, 'longitude' => 42.12,
            'translations' => self::translations(
                ['en' => 'Water supply extension', 'am' => 'የውሃ አቅርቦት ማስፋፊያ', 'om' => 'Babal\'ina dhiyeessii bishaanii'],
                ['en' => ['summary' => 'A sample project to test the project pages. The numbers are not real.']],
            ),
        ], $actor);
        app(SaveProject::class)->handle(new Project, [
            'sector_id' => $sector->id, 'status' => 'completed',
            'ends_on' => CarbonImmutable::now()->subMonth()->toDateString(),
            'translations' => self::translations(['en' => 'Borehole rehabilitation', 'am' => 'የጉድጓድ ውሃ ጥገና', 'om' => 'Haaromsa boolla bishaanii']),
        ], $actor);

        app(SaveStatistic::class)->handle(new Statistic, [
            'value' => 12, 'sector_id' => $sector->id, 'project_id' => $water->id, 'as_of' => CarbonImmutable::now()->toDateString(),
            'translations' => self::translations(['en' => 'Water points built', 'am' => 'የተገነቡ የውሃ ቦኖዎች', 'om' => 'Bakkeewwan bishaanii ijaaraman'], [], 'label'),
        ], $actor);
        app(SaveStatistic::class)->handle(new Statistic, [
            'value' => 4500,
            'translations' => self::translations(['en' => 'People reached', 'am' => 'ተጠቃሚ የሆኑ ሰዎች', 'om' => 'Namoota fayyadaman'], [], 'label'),
        ], $actor);
    }

    private function form(User $actor): void
    {
        app(SaveForm::class)->handle(new Form, [
            'type' => 'feedback', 'response_days' => 3,
            'translations' => self::translations(
                ['en' => 'Feedback form', 'am' => 'የአስተያየት ቅጽ', 'om' => 'Unka yaadaa'],
                ['en' => ['intro' => 'A sample form to test the inbox. Messages sent here are test messages.']],
            ),
            'fields' => [
                ['type' => FormField::SELECT, 'required' => true, 'label' => ['en' => 'Topic', 'am' => 'ርዕስ', 'om' => 'Mata duree'],
                    'choices' => ['en' => "Services\nEvents\nOther", 'am' => "አገልግሎቶች\nሁነቶች\nሌላ", 'om' => "Tajaajila\nSagantaalee\nKan biraa"]],
                ['type' => FormField::TEXTAREA, 'required' => true, 'label' => ['en' => 'Your message', 'am' => 'መልእክትዎ', 'om' => 'Ergaa keessan']],
            ],
        ], $actor);
    }

    private function pdf(string $name, string $text): Media
    {
        $stream = 'BT /F1 18 Tf 72 720 Td ('.$text.') Tj ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $number => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($number + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        File::ensureDirectoryExists(storage_path('app/media-work'));
        $path = storage_path('app/media-work/sample-'.Str::uuid().'.pdf');
        file_put_contents($path, $pdf);
        try {
            return app(UploadMedia::class)->handle($path, $name);
        } finally {
            @unlink($path);
        }
    }

    /** Deletes every record whose English, Amharic or Afaan Oromo title carries the sample label. */
    private function remove(): int
    {
        $count = 0;
        $labelled = function (string $model, string $field) {
            $query = $model::query();
            foreach (self::LABEL as $locale => $label) {
                $query->orWhere(fn ($q) => $q->where('locale', $locale)->where($field, 'like', $label.'%'));
            }

            return $query;
        };

        foreach ([[EventTranslation::class, Event::class, 'event_id'], [DocumentTranslation::class, Document::class, 'document_id'],
            [ProjectTranslation::class, Project::class, 'project_id'], [FormTranslation::class, Form::class, 'form_id']] as [$translation, $model, $key]) {
            $ids = $labelled($translation, 'title')->pluck($key);
            if ($model === Form::class) {
                FormSubmission::query()->whereIn('form_id', $ids)->delete();
            }
            foreach ($model::query()->withTrashed()->whereKey($ids)->get() as $record) {
                $record->forceDelete();
                $count++;
            }
        }
        $count += Statistic::query()->whereKey($labelled(StatisticTranslation::class, 'label')->pluck('statistic_id'))->delete();
        foreach (Category::query()->withTrashed()->where('type', Category::TYPE_SECTOR)->whereHas('translations', fn ($q) => $q->where('locale', 'en')->where('name', 'like', self::LABEL['en'].'%'))->get() as $sector) {
            $sector->forceDelete();
            $count++;
        }

        return $count;
    }
}
