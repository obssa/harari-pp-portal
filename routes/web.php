<?php

use App\Domain\Translation\Support\Locales;
use App\Http\Controllers\Public\EventController;
use App\Http\Controllers\Public\FeedController;
use App\Http\Controllers\Public\GalleryController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\LeaderController;
use App\Http\Controllers\Public\LeagueController;
use App\Http\Controllers\Public\MediaFileController;
use App\Http\Controllers\Public\NewsController;
use App\Http\Controllers\Public\OfficeController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\PreviewController;
use App\Http\Controllers\Public\RedirectToPreferredLocale;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\StructureController;
use App\Http\Controllers\Public\VideoController;
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
        Route::get('/', HomeController::class)->name('home');
        Route::get('news', [NewsController::class, 'index'])->name('news.index');
        Route::get('news/{slug}', [NewsController::class, 'show'])
            ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
            ->name('news.show');
        Route::get('feed.xml', [FeedController::class, 'rss'])->name('feed.rss');
        Route::get('feed.json', [FeedController::class, 'json'])->name('feed.json');

        Route::get('leaders', [LeaderController::class, 'index'])->name('leaders.index');
        Route::get('leaders/{slug}', [LeaderController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('leaders.show');
        Route::get('offices', [OfficeController::class, 'index'])->name('offices.index');
        Route::get('offices/{slug}', [OfficeController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('offices.show');
        Route::get('leagues', [LeagueController::class, 'index'])->name('leagues.index');
        Route::get('leagues/{slug}', [LeagueController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('leagues.show');
        Route::get('structure', StructureController::class)->name('structure');
        Route::get('events', [EventController::class, 'index'])->name('events.index');
        Route::get('events/feed.ics', [EventController::class, 'feed'])->name('events.feed');
        Route::get('events/{slug}.ics', [EventController::class, 'ics'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('events.ics');
        Route::get('events/{slug}', [EventController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('events.show');
        Route::get('gallery', [GalleryController::class, 'index'])->name('gallery.index');
        Route::get('videos', [VideoController::class, 'index'])->name('videos.index');
        Route::get('videos/{slug}', [VideoController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('videos.show');
        Route::get('live', [VideoController::class, 'live'])->name('videos.live');
        Route::get('gallery/{slug}', [GalleryController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('gallery.show');
        Route::get('search', SearchController::class)
            ->withoutMiddleware(CachePublicPages::class)
            ->middleware('throttle:search')
            ->name('search');

        // Pages (about/history ...): last, so fixed sections such as news always win.
        Route::get('{path}', PageController::class)
            ->where('path', '[a-z0-9]+(?:-[a-z0-9]+)*(?:/[a-z0-9]+(?:-[a-z0-9]+)*){0,2}')
            ->name('page.show');
    });
