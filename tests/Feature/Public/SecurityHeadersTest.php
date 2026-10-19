<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Media\Actions\UploadMedia;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Tests\Support\TestFiles;

it('sends a strict content security policy with a fresh nonce on public pages', function () {
    $first = $this->get('/en')->assertOk();
    $nonce = Vite::cspNonce();
    $policy = (string) $first->headers->get('Content-Security-Policy');

    expect($nonce)->toBeString()->not->toBeEmpty()
        ->and($policy)->toContain("script-src 'self' 'nonce-{$nonce}'")
        ->toContain("style-src 'self' 'nonce-{$nonce}'")
        ->toContain("object-src 'none'", "base-uri 'self'", "frame-ancestors 'none'", "form-action 'self'")
        ->not->toContain('unsafe-eval')
        ->not->toContain("script-src 'self' 'unsafe-inline'");

    $second = (string) $this->get('/en')->headers->get('Content-Security-Policy');
    expect($second)->not->toBe($policy);
});

it('sends the other security headers on every response', function (string $path) {
    $response = $this->get($path);

    $response->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()', 'geolocation=()');
})->with(['/en', '/up', '/admin/login', '/does-not-exist']);

it('adds HSTS only over HTTPS', function () {
    expect($this->get('https://localhost/en')->headers->get('Strict-Transport-Security'))
        ->toBe('max-age=31536000; includeSubDomains')
        ->and($this->get('http://localhost/en')->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('relaxes the policy for the admin panel, which needs Alpine and inline scripts', function () {
    $this->actingAs(staff(SystemRole::Admin));

    $policy = (string) $this->get('/admin')->headers->get('Content-Security-Policy');

    expect($policy)->toBe(SecurityHeaders::adminPolicy())
        ->toContain("'unsafe-eval'", "object-src 'none'", "frame-ancestors 'none'");
});

it('keeps the sandbox policy the media route sets for SVG files', function () {
    Storage::fake('media');
    Storage::fake('public');
    $media = app(UploadMedia::class)->handle(TestFiles::svg('<rect width="1" height="1"/>'), 'logo.svg');

    expect($this->get("/media/{$media->uuid}/logo.svg")->headers->get('Content-Security-Policy'))
        ->toContain('sandbox')
        ->not->toContain('nonce-');
});

it('treats only admin and Livewire paths as admin', function (string $path, bool $admin) {
    expect(SecurityHeaders::isAdmin(Request::create($path)))->toBe($admin);
})->with([
    ['/admin', true],
    ['/admin/media', true],
    ['/livewire/update', true],
    ['/en', false],
    ['/administration', false],
    ['/media/x/y.jpg', false],
]);
