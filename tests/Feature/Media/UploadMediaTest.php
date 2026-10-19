<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Exceptions\RejectedUpload;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaFolder;
use App\Domain\Media\Support\ImageConversions;
use App\Domain\Sites\Models\Site;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\TestFiles;

beforeEach(function () {
    Storage::fake('media');
    Storage::fake('public');
    config(['portal.media.avif' => false]);
});

function upload(string $path, string $name, mixed ...$rest): Media
{
    return app(UploadMedia::class)->handle($path, $name, ...$rest);
}

it('adds a photo to the site library with its details', function () {
    $author = staff(SystemRole::Author);
    $folder = MediaFolder::query()->create(['name' => 'Events']);

    $media = upload(TestFiles::image(800, 600), 'Town hall_meeting-2026.jpg', $author, $folder);

    expect($media->collection_name)->toBe(Media::LIBRARY)
        ->and($media->site_id)->toBe(Site::current()->id)
        ->and($media->model_type)->toBe(Site::class)
        ->and($media->folder_id)->toBe($folder->id)
        ->and($media->uploaded_by)->toBe($author->id)
        ->and($media->kind)->toBe(MediaKind::Image)
        ->and($media->name)->toBe('Town hall meeting 2026')
        ->and($media->file_name)->toBe('town-hall-meeting-2026.jpg')
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and([$media->width, $media->height])->toBe([800, 600])
        ->and($media->disk)->toBe('media')
        ->and($media->conversions_disk)->toBe('public')
        ->and(Storage::disk('media')->exists($media->getPathRelativeToRoot()))->toBeTrue()
        ->and(Activity::query()->where('log_name', 'media')->where('subject_id', $media->id)->exists())->toBeTrue();
});

it('removes EXIF data such as location from photos', function () {
    $source = TestFiles::jpegWithExif();
    expect((string) file_get_contents($source))->toContain(TestFiles::EXIF_SECRET);

    $media = upload($source, 'phone-photo.jpg');
    $stored = (string) Storage::disk('media')->get($media->getPathRelativeToRoot());

    expect($stored)->not->toContain(TestFiles::EXIF_SECRET)->not->toContain('Exif');
});

it('scales very large photos down to the maximum edge', function () {
    config(['portal.media.max_edge' => 500]);

    $media = upload(TestFiles::image(1000, 400), 'panorama.jpg');

    expect([$media->width, $media->height])->toBe([500, 200]);
    $size = getimagesize(Storage::disk('media')->path($media->getPathRelativeToRoot()));
    expect([$size[0] ?? null, $size[1] ?? null])->toBe([500, 200]);
});

it('stores the real type even when the name lies', function () {
    $media = upload(TestFiles::image(50, 50, 'png'), 'logo.jpg');

    expect($media->file_name)->toBe('logo.png')->and($media->mime_type)->toBe('image/png');
});

it('gives Ethiopic file names a safe stored name', function () {
    $media = upload(TestFiles::image(50, 50), 'ሐረር.jpg');

    expect($media->name)->toBe('ሐረር')
        ->and($media->file_name)->toMatch('/^[a-z0-9-]+\.jpg$/');
});

it('makes the preview and the cropped copies, skipping sizes larger than the photo', function () {
    $media = upload(TestFiles::image(1000, 700), 'rally.jpg')->refresh();

    expect($media->hasGeneratedConversion(ImageConversions::THUMB))->toBeTrue()
        ->and($media->hasGeneratedConversion('landscape-480'))->toBeTrue()
        ->and($media->hasGeneratedConversion('landscape-960'))->toBeTrue()
        ->and($media->getGeneratedConversions()->has('landscape-1600'))->toBeFalse()
        ->and($media->hasGeneratedConversion('square-320'))->toBeTrue()
        ->and($media->hasGeneratedConversion('photo-600'))->toBeTrue();

    $path = Storage::disk('public')->path($media->getPathRelativeToRoot('landscape-960'));
    expect(pathinfo($path, PATHINFO_EXTENSION))->toBe('webp')
        ->and(getimagesize($path))->toMatchArray([0 => 960, 1 => 540, 'mime' => 'image/webp']);
});

it('also makes AVIF copies when the server can', function () {
    if (! ImageConversions::serverSupportsAvif()) {
        $this->markTestSkipped('GD without AVIF support');
    }
    config(['portal.media.avif' => 'auto']);

    $media = upload(TestFiles::image(500, 300), 'flag.jpg')->refresh();
    $avif = Storage::disk('public')->path($media->getPathRelativeToRoot('landscape-480-avif'));

    expect($media->hasGeneratedConversion('landscape-480-avif'))->toBeTrue()
        ->and(getimagesize($avif)['mime'] ?? null)->toBe('image/avif');
});

it('turns AVIF off when configured, or when the server cannot write it', function () {
    config(['portal.media.avif' => 'false']);
    expect(ImageConversions::avifEnabled())->toBeFalse();

    config(['portal.media.avif' => 'true']);
    expect(ImageConversions::avifEnabled())->toBe(ImageConversions::serverSupportsAvif());
});

it('crops around the focal point and remakes the crops when it moves', function () {
    $media = upload(TestFiles::splitImage(800, 400), 'split.png');

    $centre = function (Media $media): array {
        $image = imagecreatefromwebp(Storage::disk('public')->path($media->getPathRelativeToRoot('square-320')));
        $rgb = imagecolorat($image, 160, 160);

        return [($rgb >> 16) & 0xFF, $rgb & 0xFF];
    };

    $media->update(['focal_x' => 5, 'focal_y' => 50]);
    [$red, $blue] = $centre($media->refresh());
    expect($red)->toBeGreaterThan(200)->and($blue)->toBeLessThan(60);

    $media->update(['focal_x' => 95]);
    [$red, $blue] = $centre($media->refresh());
    expect($blue)->toBeGreaterThan(200)->and($red)->toBeLessThan(60);
});

it('stores SVGs sanitised and makes no copies of them', function () {
    $media = upload(TestFiles::svg('<rect width="5" height="5" onclick="alert(1)"/><script>alert(2)</script>'), 'emblem.svg');

    $stored = (string) Storage::disk('media')->get($media->getPathRelativeToRoot());
    expect($media->kind)->toBe(MediaKind::Vector)
        ->and($stored)->toContain('<rect')->not->toContain('onclick')->not->toContain('<script')
        ->and($media->refresh()->getGeneratedConversions()->all())->toBe([]);
});

it('stores documents unchanged', function () {
    $source = TestFiles::docx();
    $media = upload($source, 'Party programme.docx');

    expect($media->kind)->toBe(MediaKind::Document)
        ->and($media->file_name)->toBe('party-programme.docx')
        ->and(Storage::disk('media')->get($media->getPathRelativeToRoot()))->toBe(file_get_contents($source));
});

it('stores nothing when a file is refused', function () {
    expect(fn () => upload(TestFiles::text('<?php echo 1;', 'jpg'), 'x.jpg'))->toThrow(RejectedUpload::class)
        ->and(Media::query()->count())->toBe(0)
        ->and(Storage::disk('media')->allFiles())->toBe([]);
});

it('gives each file a URL: copies on the public disk, originals through the media route', function () {
    $media = upload(TestFiles::image(500, 300), 'flag.jpg')->refresh();

    expect($media->getUrl())->toStartWith(url("/media/{$media->uuid}/flag.jpg"))
        ->and($media->getUrl('landscape-480'))->toContain("/storage/library/{$media->uuid}/conversions/flag-landscape-480.webp");
});
