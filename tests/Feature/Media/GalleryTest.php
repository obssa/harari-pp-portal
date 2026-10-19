<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Homepage\Support\HomepageLayout;
use App\Domain\Media\Actions\SaveGallery;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Models\Gallery;
use App\Domain\Media\Models\Media;
use App\Filament\Resources\Galleries\Pages\CreateGallery;
use App\Filament\Resources\Galleries\Pages\EditGallery;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

function galleryPhoto(string $name = 'photo.jpg'): Media
{
    $image = imagecreatetruecolor(400, 300);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 120, 60));
    File::ensureDirectoryExists(storage_path('app/media-work'));
    $path = storage_path('app/media-work/test-'.Str::uuid().'.jpg');
    imagejpeg($image, $path, 85);

    $media = app(UploadMedia::class)->handle($path, $name);
    $media->forceFill(['alt' => ['en' => 'Sample photo', 'am' => 'ናሙና ፎቶ', 'om' => 'Suuraa fakkeenyaa'], 'credit' => 'Sample credit'])->save();

    return $media;
}

/**
 * @param  array<string, array<string, mixed>>  $translations
 * @param  list<Media>  $photos
 * @param  array<string, mixed>  $data
 */
function makeGallery(array $translations, array $photos = [], array $data = []): Gallery
{
    return app(SaveGallery::class)->handle(new Gallery, [
        'translations' => $translations,
        'items' => array_map(fn (Media $m): array => ['media_id' => $m->id, 'caption' => ['en' => "Caption {$m->id}"]], $photos),
        ...$data,
    ], staff(SystemRole::Publisher));
}

it('saves albums with ordered photos, captions and the first photo as cover', function () {
    [$one, $two] = [galleryPhoto('one.jpg'), galleryPhoto('two.jpg')];
    $gallery = app(SaveGallery::class)->handle(new Gallery, [
        'translations' => ['en' => ['title' => 'Sample album', 'published' => true]],
        'items' => [
            ['media_id' => $two->id, 'caption' => ['en' => ' Second first ', 'am' => '']],
            ['media_id' => $one->id],
            ['media_id' => $two->id],
            ['media_id' => 999999],
        ],
        'taken_on' => '2026-10-15',
    ], staff(SystemRole::Editor));

    expect($gallery->items->pluck('media_id')->all())->toBe([$two->id, $one->id])
        ->and($gallery->items->first()?->caption)->toBe(['en' => 'Second first'])
        ->and($gallery->cover_id)->toBe($two->id)
        ->and($gallery->taken_on?->toDateString())->toBe('2026-10-15')
        ->and($gallery->translation('en')?->getAttribute('status'))->toBe(ContentStatus::Published)
        ->and($two->fresh()?->isUsed())->toBeTrue();

    expect(fn () => app(SaveGallery::class)->handle(new Gallery, ['translations' => ['en' => ['title' => ' ']]], staff(SystemRole::Editor)))
        ->toThrow(ValidationException::class);
});

it('shows published albums, their photos with a lightbox, and hides drafts', function () {
    $photos = [galleryPhoto('a.jpg'), galleryPhoto('b.jpg')];
    makeGallery(['en' => ['title' => 'Sample visit album', 'summary' => 'Sample summary', 'published' => true]], $photos, ['taken_on' => '2026-10-10']);
    makeGallery(['en' => ['title' => 'Sample draft album']], $photos);

    $this->get('/en/gallery')->assertOk()->assertSee('Sample visit album')->assertSee(trans_choice('gallery.photos', 2, ['count' => 2], 'en'))->assertDontSee('Sample draft album');

    $html = (string) $this->get('/en/gallery/sample-visit-album')
        ->assertOk()
        ->assertSee('href="#photo-2"', escape: false)
        ->assertSee('id="photo-2"', escape: false)
        ->assertSee('Caption '.$photos[1]->id)
        ->assertSee(__('content.photo_credit', ['credit' => 'Sample credit'], 'en'))
        ->assertDontSee(__('gallery.download', [], 'en'))
        ->getContent();
    expect($html)->toContain('"@type":"ImageGallery"');

    $this->get('/en/gallery/sample-draft-album')->assertNotFound();
});

it('offers the original photos for download only when allowed', function () {
    $photo = galleryPhoto('allowed.jpg');
    makeGallery(['en' => ['title' => 'Sample open album', 'published' => true]], [$photo], ['allow_download' => true]);

    $this->get('/en/gallery/sample-open-album')
        ->assertSee(__('gallery.download', [], 'en'))
        ->assertSee(route('media.file', ['uuid' => $photo->uuid, 'name' => $photo->file_name]), escape: false);
});

it('shows Amharic albums to Harari readers and the newest album on the homepage', function () {
    makeGallery(['am' => ['title' => 'የናሙና ፎቶ ስብስብ', 'published' => true]], [galleryPhoto()]);

    $this->get('/har/gallery')->assertOk()->assertSee('የናሙና ፎቶ ስብስብ')->assertSee('lang="am"', escape: false);
    $this->get('/am')->assertSee('id="home-gallery"', escape: false)->assertSee('#photo-1', escape: false);
    expect(collect(app(HomepageLayout::class)->forLocale('om'))->pluck('section.key.value'))->not->toContain('gallery');
});

it('lists albums in the sitemaps', function () {
    makeGallery(['en' => ['title' => 'Sample mapped album', 'published' => true]], [galleryPhoto()]);

    $this->get('/sitemaps/en.xml')->assertSee(url('/en/gallery/sample-mapped-album'), escape: false)->assertSee('<loc>'.url('/en/gallery').'</loc>', escape: false);
});

it('lets those who manage galleries build albums in the admin', function () {
    $this->actingAs(staff(SystemRole::Author))->get('/admin/galleries')->assertForbidden();

    $this->actingAs(staff(SystemRole::MediaManager));
    $photo = galleryPhoto();

    livewire(CreateGallery::class)
        ->fillForm([
            'translations' => ['om' => ['title' => 'Kuusaa suuraa fakkeenyaa', 'published' => true]],
            'items' => [['media_id' => $photo->id, 'caption' => ['om' => 'Ibsa fakkeenyaa']]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $gallery = Gallery::query()->firstOrFail();
    expect($gallery->items()->first()?->caption)->toBe(['om' => 'Ibsa fakkeenyaa']);

    livewire(EditGallery::class, ['record' => $gallery->getRouteKey()])
        ->assertFormSet(['translations.om.title' => 'Kuusaa suuraa fakkeenyaa', 'translations.om.published' => true])
        ->fillForm(['allow_download' => true])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($gallery->fresh()?->allow_download)->toBeTrue();
});
