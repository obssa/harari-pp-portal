<?php

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Exceptions\RejectedUpload;
use App\Domain\Media\Support\InspectedFile;
use App\Domain\Media\Support\UploadInspector;
use Tests\Support\TestFiles;

function inspect(string $path, string $name): InspectedFile
{
    return app(UploadInspector::class)->inspect($path, $name);
}

function refusal(string $path, string $name): ?string
{
    try {
        inspect($path, $name);
    } catch (RejectedUpload $e) {
        return $e->reason;
    }

    return null;
}

it('identifies files by their content, not their name', function () {
    $file = inspect(TestFiles::image(40, 30), 'holiday.png');

    expect($file->kind)->toBe(MediaKind::Image)
        ->and($file->mime)->toBe('image/jpeg')
        ->and($file->extension)->toBe('jpg')
        ->and($file->width)->toBe(40)
        ->and($file->height)->toBe(30);
});

it('accepts the allowed kinds of file', function (string $path, string $name, MediaKind $kind, string $extension) {
    $file = inspect($path, $name);

    expect($file->kind)->toBe($kind)->and($file->extension)->toBe($extension);
})->with([
    'png' => fn () => [TestFiles::image(10, 10, 'png'), 'a.png', MediaKind::Image, 'png'],
    'webp' => fn () => [TestFiles::image(10, 10, 'webp'), 'a.webp', MediaKind::Image, 'webp'],
    'gif' => fn () => [TestFiles::image(10, 10, 'gif'), 'a.gif', MediaKind::Image, 'gif'],
    'svg' => fn () => [TestFiles::svg('<rect width="5" height="5"/>'), 'logo.svg', MediaKind::Vector, 'svg'],
    'docx' => fn () => [TestFiles::docx(), 'report.docx', MediaKind::Document, 'docx'],
    'csv' => fn () => [TestFiles::text("name,votes\nA,1\n", 'csv'), 'results.csv', MediaKind::Document, 'csv'],
    'txt' => fn () => [TestFiles::text("Plain notes\n"), 'notes.txt', MediaKind::Document, 'txt'],
]);

it('refuses PHP hidden behind an image name', function () {
    expect(refusal(TestFiles::text("<?php system(\$_GET['c']); ?>", 'jpg'), 'photo.php.jpg'))->toBe('type_not_allowed');
});

it('refuses HTML and scripts', function () {
    expect(refusal(TestFiles::text('<!doctype html><html><body><script>alert(1)</script></body></html>', 'html'), 'page.txt'))
        ->toBe('type_not_allowed');
});

it('refuses text files carrying PHP code', function () {
    expect(refusal(TestFiles::text("id,name\n1,<?= 'x' ?>\n", 'csv'), 'list.csv'))->toBe('contains_code');
});

it('refuses Office files with macros', function () {
    expect(refusal(TestFiles::docx(withMacros: true), 'minutes.docx'))->toBe('contains_code');
});

it('refuses empty files', function () {
    expect(refusal(TestFiles::text(''), 'empty.txt'))->toBe('empty');
});

it('refuses files over the limit for their kind', function () {
    config(['portal.media.max_mb.document' => 0]);

    expect(refusal(TestFiles::text("some text\n"), 'notes.txt'))->toBe('too_large');
});

it('refuses images with more pixels than allowed, before decoding them', function () {
    expect(refusal(TestFiles::pngClaiming(20000, 20000), 'bomb.png'))->toBe('too_many_pixels');
});

it('refuses damaged images', function () {
    $path = TestFiles::path('jpg');
    file_put_contents($path, "\xFF\xD8\xFF\xE0".str_repeat('x', 200));

    expect(refusal($path, 'broken.jpg'))->toBe('corrupt');
});

it('lists the accepted content types and the largest upload', function () {
    expect(MediaKind::acceptedMimeTypes())->toContain('image/jpeg', 'image/svg+xml', 'application/pdf', 'video/mp4')
        ->not->toContain('text/html', 'application/x-php', 'image/svg')
        ->and(MediaKind::largestUploadKb())->toBe(50 * 1024)
        ->and(MediaKind::fromMime('IMAGE/JPEG; charset=binary'))->toBe(MediaKind::Image)
        ->and(MediaKind::fromMime(null))->toBeNull()
        ->and(MediaKind::Document->opensInline('application/pdf'))->toBeTrue()
        ->and(MediaKind::Document->opensInline('text/csv'))->toBeFalse();
});
