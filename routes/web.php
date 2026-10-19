<?php

use App\Domain\Translation\Support\Locales;
use App\Http\Controllers\Public\RedirectToPreferredLocale;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

// "/" picks the visitor's language (cookie, then browser, then default).
Route::get('/', RedirectToPreferredLocale::class)->name('root');

// Every public page lives under its language: /en, /am, /om, /har (PLAN.md §3).
Route::prefix('{locale}')
    ->where(['locale' => implode('|', Locales::all())])
    ->middleware(SetLocale::class)
    ->group(function (): void {
        Route::view('/', 'pages.home')->name('home');
    });
