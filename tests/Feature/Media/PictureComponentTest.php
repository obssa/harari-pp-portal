<?php

use App\Domain\Media\Actions\UploadMedia;
use App\View\Components\Picture;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestFiles;

beforeEach(function () {
    Storage::fake('media');
    Storage::fake('public');
    config(['portal.media.avif' => false]);
});

it('renders responsive WebP sources, size, focal point and alt text', function () {
    $media = app(UploadMedia::class)->handle(TestFiles::image(1000, 700), 'rally.jpg');
    $media->update(['alt' => ['en' => 'Supporters at the rally', 'am' => 'በሰልፉ ላይ ደጋፊዎች'], 'focal_x' => 30, 'focal_y' => 40]);
    $media->refresh();

    app()->setLocale('har');
    $html = Blade::render('<x-picture :media="$media" sizes="50vw" class="hero" />', ['media' => $media]);

    expect($html)->toContain('<picture>', 'type="image/webp"', 'sizes="50vw"', '480w', '960w')
        ->not->toContain('1600w')
        ->not->toContain('image/avif')
        ->toContain('alt="በሰልፉ ላይ ደጋፊዎች"')
        ->toContain('width="480"', 'height="270"')
        ->toContain('object-position: 30% 40%', 'class="hero"', 'loading="lazy"');
});

it('shows the original while copies are still queued', function () {
    $media = app(UploadMedia::class)->handle(TestFiles::image(300, 200), 'flag.jpg');
    $media->forceFill(['generated_conversions' => []])->saveQuietly();
    $media->refresh();

    $html = Blade::render('<x-picture :media="$media" />', ['media' => $media]);

    expect($html)->toContain('src="'.e($media->getUrl()).'"', 'width="300"')->not->toContain('<source');
});

it('renders nothing without an image', function () {
    expect(trim(Blade::render('<x-picture :media="null" />')))->toBe('')
        ->and((new Picture(null))->shouldRender())->toBeFalse();
});

it('uses the given alt text and priority for the main image', function () {
    $media = app(UploadMedia::class)->handle(TestFiles::svg('<rect width="1" height="1"/>'), 'logo.svg');

    $html = Blade::render(
        '<x-picture :media="$media" alt="Party logo" loading="eager" fetchpriority="high" />',
        ['media' => $media],
    );

    expect($html)->toContain('alt="Party logo"', 'loading="eager"', 'fetchpriority="high"', '/media/'.$media->uuid.'/logo.svg');
});
