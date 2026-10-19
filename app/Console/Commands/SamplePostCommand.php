<?php

namespace App\Console\Commands;

use App\Domain\Content\Actions\RecordRevision;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Enums\PostType;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Import\Support\StoryText;
use App\Domain\Media\Actions\SyncMediaUsage;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * One clearly labelled sample story in English, Amharic and Afaan Oromo
 * (Harari falls back to Amharic), published, so the staging site can be
 * checked end to end in every language (Phase 2 gate). Never for production.
 */
final class SamplePostCommand extends Command
{
    public const SLUG = 'sample-a-story-in-four-languages';

    /** @var array<string, array{title: string, excerpt: string, paragraphs: list<string>, alt: string}> */
    private const TEXT = [
        'en' => [
            'title' => 'Sample: a story in four languages',
            'excerpt' => 'This is sample content for testing the portal. It is not an official statement.',
            'paragraphs' => [
                'This sample story shows how a news post looks in English, Amharic, Afaan Oromo and Harari.',
                'It is used to test the site and will be removed before launch.',
            ],
            'alt' => 'Sample image with the portal colours',
        ],
        'am' => [
            'title' => 'ናሙና፦ በአራት ቋንቋዎች የቀረበ ዜና',
            'excerpt' => 'ይህ ፖርታሉን ለመሞከር የተዘጋጀ ናሙና ይዘት ነው። ይፋዊ መግለጫ አይደለም።',
            'paragraphs' => [
                'ይህ ናሙና ዜና አንድ ጽሑፍ በእንግሊዝኛ፣ በአማርኛ፣ በአፋን ኦሮሞና በሐረሪ እንዴት እንደሚታይ ያሳያል።',
                'ድረ ገጹን ለመሞከር የሚያገለግል ሲሆን ከመጀመሩ በፊት ይወገዳል።',
            ],
            'alt' => 'የፖርታሉ ቀለማት ያሉበት ናሙና ምስል',
        ],
        'om' => [
            'title' => 'Fakkeenya: oduu afaanota afuriin',
            'excerpt' => "Kun qabiyyee fakkeenyaa portaalicha qorachuuf qophaa'e dha. Ibsa ifaa miti.",
            'paragraphs' => [
                "Oduun fakkeenyaa kun barreeffamni tokko Ingiliffaan, Amaariffaan, Afaan Oromootiin fi Harariin akkamitti akka mul'atu agarsiisa.",
                "Marsariitii qorachuuf kan tajaajilu yoo ta'u, osoo hin eegalamin dura ni haqama.",
            ],
            'alt' => 'Suuraa fakkeenyaa halluu portaalichaa qabu',
        ],
    ];

    protected $signature = 'portal:sample-post {--remove : Delete the sample story}';

    protected $description = 'Publish (or remove) one labelled sample story in every language, for testing outside production';

    public function handle(UploadMedia $upload, SyncMediaUsage $usage, RecordRevision $revisions): int
    {
        if (app()->isProduction()) {
            $this->error('Sample content is never added to production.');

            return self::FAILURE;
        }

        $existing = Post::withTrashed()->whereHas('translations', fn ($q) => $q->where('locale', 'en')->where('slug', self::SLUG))->first();

        if ($this->option('remove')) {
            $existing?->forceDelete();
            $this->info($existing ? 'Sample story removed.' : 'No sample story.');

            return self::SUCCESS;
        }

        if ($existing !== null && $existing->trashed()) {
            $existing->forceDelete();
            $existing = null;
        }

        if ($existing !== null) {
            $this->info('Sample story already published: '.route('news.show', ['locale' => 'en', 'slug' => self::SLUG]));

            return self::SUCCESS;
        }

        $image = $this->image($upload);

        $post = DB::transaction(function () use ($image, $usage, $revisions): Post {
            $post = new Post;
            $post->fill(['type' => PostType::News, 'source_locale' => 'en', 'featured_image_id' => $image->id])->save();

            foreach (self::TEXT as $locale => $text) {
                /** @var PostTranslation $translation */
                $translation = $post->putTranslation($locale, [
                    'title' => $text['title'],
                    'slug' => $locale === 'en' ? self::SLUG : null,
                    'excerpt' => $text['excerpt'],
                    'body' => StoryText::body($text['paragraphs']),
                    'status' => ContentStatus::Published,
                ]);
                $revisions->handle($post, $translation, 'Sample content');
            }
            $usage->handle($post, 'featured_image', [$image->id]);

            return $post;
        });

        foreach ($post->translations()->get() as $translation) {
            $this->line(route('news.show', ['locale' => $translation->locale, 'slug' => $translation->slug]));
        }

        return self::SUCCESS;
    }

    /** A plain picture in the portal colours, labelled as a sample. */
    private function image(UploadMedia $upload): Media
    {
        $canvas = imagecreatetruecolor(1600, 900);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 49, 81, 237));
        imagefilledrectangle($canvas, 0, 820, 1600, 900, (int) imagecolorallocate($canvas, 199, 110, 40));
        imagestring($canvas, 5, 60, 60, 'SAMPLE', (int) imagecolorallocate($canvas, 255, 255, 255));

        File::ensureDirectoryExists(storage_path('app/media-work'));
        $path = storage_path('app/media-work/sample-'.Str::uuid().'.jpg');
        imagejpeg($canvas, $path, 85);

        try {
            $media = $upload->handle($path, 'sample-story.jpg');
        } finally {
            @unlink($path);
        }

        $media->forceFill([
            'alt' => array_map(fn (array $text): string => $text['alt'], self::TEXT),
            'credit' => 'Sample',
        ])->save();

        return $media;
    }
}
