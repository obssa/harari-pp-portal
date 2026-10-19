<?php

use App\Domain\Translation\Support\Locales;
use App\Http\Controllers\Public\FeedController;
use App\Http\Controllers\Public\MediaFileController;
use App\Http\Controllers\Public\NewsController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\PreviewController;
use App\Http\Controllers\Public\RedirectToPreferredLocale;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\SitemapController;
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

// Signed preview of any language of a post, drafts included (PLAN.md §7).
Route::get('/preview/posts/{post}/{language}', PreviewController::class)
    ->middleware('signed')
    ->withoutMiddleware($stateless)
    ->name('preview.post');

// Search engines (ADR-0015).
Route::get('/robots.txt', RobotsController::class)->withoutMiddleware($stateless)->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->withoutMiddleware($stateless)->name('sitemap.index');
Route::get('/sitemaps/{language}.xml', [SitemapController::class, 'language'])
    ->whereIn('language', Locales::all())
    ->withoutMiddleware($stateless)
    ->name('sitemap.language');

// Every public page lives under its language: /en, /am, /om, /har (PLAN.md §3).
Route::prefix('{locale}')
    ->where(['locale' => implode('|', Locales::all())])
    ->middleware([SetLocale::class, CachePublicPages::class])
    ->withoutMiddleware($stateless)
    ->group(function (): void {
        Route::view('/', 'pages.home')->name('home');
        Route::get('news', [NewsController::class, 'index'])->name('news.index');
        Route::get('news/{slug}', [NewsController::class, 'show'])
            ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
            ->name('news.show');
        Route::get('feed.xml', [FeedController::class, 'rss'])->name('feed.rss');
        Route::get('feed.json', [FeedController::class, 'json'])->name('feed.json');

        // Pages (about/history ...): last, so fixed sections such as news always win.
        Route::get('{path}', PageController::class)
            ->where('path', '[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*){0,2}')
            ->name('page.show');
    });
