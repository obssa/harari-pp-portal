<?php

use App\Domain\Translation\Support\Locales;
use App\Http\Controllers\Public\MediaFileController;
use App\Http\Controllers\Public\RedirectToPreferredLocale;
use App\Http\Middleware\SetLocale;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// "/" picks the visitor's language (cookie, then browser, then default).
Route::get('/', RedirectToPreferredLocale::class)->name('root');

// Media library originals (ADR-0009). No session or cookies, so Cloudflare can cache them.
Route::get('/media/{uuid}/{name}', MediaFileController::class)
    ->whereUuid('uuid')
    ->where('name', '[A-Za-z0-9][A-Za-z0-9._-]{0,190}')
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
    ])
    ->name('media.file');

// Every public page lives under its language: /en, /am, /om, /har (PLAN.md §3).
Route::prefix('{locale}')
    ->where(['locale' => implode('|', Locales::all())])
    ->middleware(SetLocale::class)
    ->group(function (): void {
        Route::view('/', 'pages.home')->name('home');
    });
