<?php

use App\Domain\Translation\Support\Locales;
use App\Http\Controllers\Public\MediaFileController;
use App\Http\Controllers\Public\RedirectToPreferredLocale;
use App\Http\Middleware\CachePublicPages;
use App\Http\Middleware\OutsideProductionOnly;
use App\Http\Middleware\SetLocale;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
| Public pages and files carry no session (ADR-0010): no session cookie, so
| Cloudflare can cache them. Pages with forms opt back in when they arrive.
*/
$stateless = [StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class];

// "/" picks the visitor's language (cookie, then browser, then default).
Route::get('/', RedirectToPreferredLocale::class)
    ->withoutMiddleware($stateless)
    ->name('root');

// Media library originals (ADR-0009). No cookies at all, so Cloudflare can cache them.
Route::get('/media/{uuid}/{name}', MediaFileController::class)
    ->whereUuid('uuid')
    ->where('name', '[A-Za-z0-9][A-Za-z0-9._-]{0,190}')
    ->withoutMiddleware([...$stateless, EncryptCookies::class, AddQueuedCookiesToResponse::class])
    ->name('media.file');

// Component styleguide for designers and reviewers; never in production (PLAN.md §12).
Route::view('/styleguide', 'styleguide')
    ->middleware(OutsideProductionOnly::class)
    ->withoutMiddleware($stateless)
    ->name('styleguide');

// Every public page lives under its language: /en, /am, /om, /har (PLAN.md §3).
Route::prefix('{locale}')
    ->where(['locale' => implode('|', Locales::all())])
    ->middleware([SetLocale::class, CachePublicPages::class])
    ->withoutMiddleware($stateless)
    ->group(function (): void {
        Route::view('/', 'pages.home')->name('home');
    });
