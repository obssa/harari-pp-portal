<?php

use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Models\Media;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestFiles;

beforeEach(function () {
    Storage::fake('media');
    Storage::fake('public');
    config(['portal.media.avif' => false]);
});

function stored(string $path, string $name): Media
{
    return app(UploadMedia::class)->handle($path, $name);
}

it('serves an original with its type, no sniffing and long caching', function () {
    $media = stored(TestFiles::image(100, 80), 'flag.jpg');

    $response = $this->get("/media/{$media->uuid}/flag.jpg");

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cross-Origin-Resource-Policy', 'same-site');
    expect($response->headers->get('Cache-Control'))->toContain('immutable')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($response->headers->getCookies())->toBe([]);
});

it('runs SVGs in a sandbox', function () {
    $media = stored(TestFiles::svg('<rect width="5" height="5"/>'), 'emblem.svg');

    $this->get("/media/{$media->uuid}/emblem.svg")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/svg+xml')
        ->assertHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox");
});

it('downloads office documents instead of opening them', function () {
    $media = stored(TestFiles::docx(), 'minutes.docx');

    $response = $this->get("/media/{$media->uuid}/minutes.docx")->assertOk();

    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('answers 404 for a wrong name, an unknown file or a file outside the library', function () {
    $media = stored(TestFiles::image(10, 10), 'flag.jpg');

    $this->get("/media/{$media->uuid}/other.jpg")->assertNotFound();
    $this->get('/media/9b1c0b1e-7d2f-4b8a-9a3e-2f6f1c0d9e11/flag.jpg')->assertNotFound();
    $this->get('/media/not-a-uuid/flag.jpg')->assertNotFound();

    $media->update(['collection_name' => 'avatars']);
    $this->get("/media/{$media->uuid}/flag.jpg")->assertNotFound();
});

it('answers 404 when the stored file is gone', function () {
    $media = stored(TestFiles::image(10, 10), 'flag.jpg');
    Storage::disk('media')->delete($media->getPathRelativeToRoot());

    $this->get("/media/{$media->uuid}/flag.jpg")->assertNotFound();
});
