<?php

use App\Domain\Translation\Support\Locales;
use App\Http\Controllers\Public\CampaignLinkController;
use App\Http\Controllers\Public\DocumentController;
use App\Http\Controllers\Public\EventController;
use App\Http\Controllers\Public\EventRegistrationController;
use App\Http\Controllers\Public\FeedController;
use App\Http\Controllers\Public\FormController;
use App\Http\Controllers\Public\GalleryController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\LeaderController;
use App\Http\Controllers\Public\LeagueController;
use App\Http\Controllers\Public\MediaFileController;
use App\Http\Controllers\Public\MemberPortalController;
use App\Http\Controllers\Public\MembershipController;
use App\Http\Controllers\Public\NewsController;
use App\Http\Controllers\Public\NewsletterController;
use App\Http\Controllers\Public\OfficeController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\PageViewController;
use App\Http\Controllers\Public\PressController;
use App\Http\Controllers\Public\PressOptOutController;
use App\Http\Controllers\Public\PreviewController;
use App\Http\Controllers\Public\ProjectController;
use App\Http\Controllers\Public\RedirectToPreferredLocale;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\SearchController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Public\SocialController;
use App\Http\Controllers\Public\StructureController;
use App\Http\Controllers\Public\VideoController;
use App\Http\Controllers\Public\VolunteerController;
use App\Http\Middleware\CachePublicPages;
use App\Http\Middleware\EnsureMemberSignedIn;
use App\Http\Middleware\NoStore;
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

// ADR-0051: the mail app's one-click unsubscribe (RFC 8058) posts without a session or form token.
Route::post('/{locale}/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])
    ->where(['locale' => implode('|', Locales::all()), 'token' => '[A-Za-z0-9]{40}'])
    ->middleware([SetLocale::class, 'throttle:forms', NoStore::class])
    ->withoutMiddleware($stateless)
    ->name('newsletter.unsubscribe');

// ADR-0052: the unsubscribe link of campaign messages; mail apps post to it without a session.
Route::get('/{locale}/u/{token}', [CampaignLinkController::class, 'show'])
    ->where(['locale' => implode('|', Locales::all()), 'token' => '[A-Za-z0-9]{12}'])
    ->middleware([SetLocale::class, 'throttle:forms', NoStore::class])
    ->withoutMiddleware($stateless)
    ->name('campaigns.unsubscribe');
Route::get('/{locale}/c/{token}/{link}', [CampaignLinkController::class, 'click'])
    ->where(['locale' => implode('|', Locales::all()), 'token' => '[A-Za-z0-9]{12}', 'link' => '[0-9]{1,3}'])
    ->middleware([SetLocale::class, 'throttle:beacon'])
    ->withoutMiddleware([...$stateless, EncryptCookies::class, AddQueuedCookiesToResponse::class])
    ->name('campaigns.click');
Route::get('/{locale}/o/{token}.gif', [CampaignLinkController::class, 'open'])
    ->where(['locale' => implode('|', Locales::all()), 'token' => '[A-Za-z0-9]{12}'])
    ->middleware([SetLocale::class, 'throttle:beacon'])
    ->withoutMiddleware([...$stateless, EncryptCookies::class, AddQueuedCookiesToResponse::class])
    ->name('campaigns.open');
Route::post('/{locale}/u/{token}', [CampaignLinkController::class, 'unsubscribe'])
    ->where(['locale' => implode('|', Locales::all()), 'token' => '[A-Za-z0-9]{12}'])
    ->middleware([SetLocale::class, 'throttle:forms', NoStore::class])
    ->withoutMiddleware($stateless)
    ->name('campaigns.unsubscribe.post');

// ADR-0054: journalists stop receiving press releases; mail apps post without a session.
Route::get('/{locale}/press/opt-out/{token}', [PressOptOutController::class, 'show'])
    ->where(['locale' => implode('|', Locales::all()), 'token' => '[A-Za-z0-9]{24}'])
    ->middleware([SetLocale::class, 'throttle:forms', NoStore::class])
    ->withoutMiddleware($stateless)
    ->name('press.opt-out');
Route::post('/{locale}/press/opt-out/{token}', [PressOptOutController::class, 'store'])
    ->where(['locale' => implode('|', Locales::all()), 'token' => '[A-Za-z0-9]{24}'])
    ->middleware([SetLocale::class, 'throttle:forms', NoStore::class])
    ->withoutMiddleware($stateless)
    ->name('press.opt-out.store');

// Cookieless page view counts (ADR-0040): no session, no cookies, nothing about the visitor stored.
Route::post('/pv', PageViewController::class)
    ->withoutMiddleware([...$stateless, EncryptCookies::class, AddQueuedCookiesToResponse::class])
    ->middleware('throttle:beacon')
    ->name('pageview');

// Search engines (ADR-0015).
Route::get('/robots.txt', RobotsController::class)->withoutMiddleware($stateless)->name('robots');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->withoutMiddleware($stateless)->name('sitemap.index');
Route::get('/sitemaps/{language}.xml', [SitemapController::class, 'language'])
    ->whereIn('language', Locales::all())
    ->withoutMiddleware($stateless)
    ->name('sitemap.language');

// Forms (ADR-0025): these pages keep the session for CSRF protection and are
// never cached. Registered before the pages below so {path} cannot catch them.
Route::prefix('{locale}')
    ->where(['locale' => implode('|', Locales::all())])
    ->middleware(SetLocale::class)
    ->group(function (): void {
        Route::get('events/{slug}/register', [EventRegistrationController::class, 'create'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('events.register');
        Route::post('events/{slug}/register', [EventRegistrationController::class, 'store'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->middleware('throttle:forms')->name('events.register.store');
        Route::get('events/ticket/{token}', [EventRegistrationController::class, 'ticket'])->where('token', '[A-Za-z0-9]{40}')->middleware(NoStore::class)->name('events.ticket');
        Route::post('events/ticket/{token}/cancel', [EventRegistrationController::class, 'cancel'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:forms')->name('events.ticket.cancel');
        Route::get('contact', [FormController::class, 'contact'])->name('contact');
        Route::get('forms/{slug}', [FormController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('forms.show');
        Route::post('forms/{slug}', [FormController::class, 'submit'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->middleware('throttle:forms')->name('forms.submit');
        Route::get('tickets', [FormController::class, 'lookup'])->middleware(['throttle:search', NoStore::class])->name('tickets.lookup');
        Route::get('tickets/{reference}', [FormController::class, 'ticket'])->where('reference', 'HPP-[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}')->middleware(['throttle:search', NoStore::class])->name('tickets.show');

        // ADR-0044: the membership application, step by step, and its status page.
        Route::get('membership/apply', [MembershipController::class, 'apply'])->middleware(NoStore::class)->name('membership.apply');
        Route::post('membership/apply/about', [MembershipController::class, 'about'])->middleware('throttle:membership')->name('membership.about');
        Route::post('membership/apply/place', [MembershipController::class, 'place'])->middleware('throttle:membership')->name('membership.place');
        Route::post('membership/apply/code', [MembershipController::class, 'sendCode'])->middleware('throttle:membership')->name('membership.code');
        Route::post('membership/apply/verify', [MembershipController::class, 'verify'])->middleware('throttle:membership')->name('membership.verify');
        Route::get('membership/done', [MembershipController::class, 'done'])->middleware(NoStore::class)->name('membership.done');
        Route::match(['get', 'post'], 'membership/status', [MembershipController::class, 'status'])->middleware(['throttle:search', NoStore::class])->name('membership.status');

        // ADR-0048: volunteers sign up.
        Route::get('volunteer', [VolunteerController::class, 'create'])->middleware(NoStore::class)->name('volunteer.create');
        Route::post('volunteer', [VolunteerController::class, 'store'])->middleware('throttle:forms')->name('volunteer.store');
        Route::get('volunteer/thanks', [VolunteerController::class, 'thanks'])->middleware(NoStore::class)->name('volunteer.thanks');

        // ADR-0051: newsletter and SMS sign-up with double opt-in, and the preference centre.
        Route::get('newsletter', [NewsletterController::class, 'create'])->middleware(NoStore::class)->name('newsletter.create');
        Route::post('newsletter', [NewsletterController::class, 'store'])->middleware('throttle:forms')->name('newsletter.store');
        Route::get('newsletter/check', [NewsletterController::class, 'check'])->middleware(NoStore::class)->name('newsletter.check');
        Route::post('newsletter/check', [NewsletterController::class, 'verify'])->middleware('throttle:membership')->name('newsletter.verify');
        Route::get('newsletter/confirm/{token}', [NewsletterController::class, 'confirm'])->where('token', '[A-Za-z0-9]{40}')->middleware(NoStore::class)->name('newsletter.confirm');
        Route::get('newsletter/preferences/{token}', [NewsletterController::class, 'preferences'])->where('token', '[A-Za-z0-9]{40}')->middleware(NoStore::class)->name('newsletter.preferences');
        Route::post('newsletter/preferences/{token}', [NewsletterController::class, 'update'])->where('token', '[A-Za-z0-9]{40}')->middleware('throttle:forms')->name('newsletter.update');

        // ADR-0049: the member portal, signed in with a code by SMS; the card check is public.
        Route::get('member/sign-in', [MemberPortalController::class, 'signIn'])->middleware(NoStore::class)->name('member.sign-in');
        Route::post('member/sign-in', [MemberPortalController::class, 'sendCode'])->middleware('throttle:membership')->name('member.send-code');
        Route::get('member/code', [MemberPortalController::class, 'code'])->middleware(NoStore::class)->name('member.code');
        Route::post('member/code', [MemberPortalController::class, 'verify'])->middleware('throttle:membership')->name('member.verify-code');
        Route::post('member/sign-out', [MemberPortalController::class, 'signOut'])->name('member.sign-out');
        Route::middleware([EnsureMemberSignedIn::class, NoStore::class])->group(function (): void {
            Route::get('member', [MemberPortalController::class, 'home'])->name('member.home');
            Route::get('member/card', [MemberPortalController::class, 'card'])->name('member.card');
            Route::get('member/profile', [MemberPortalController::class, 'profile'])->name('member.profile');
            Route::post('member/profile', [MemberPortalController::class, 'requestChange'])->middleware('throttle:forms')->name('member.request');
            Route::get('member/events', [MemberPortalController::class, 'events'])->name('member.events');
            Route::get('member/documents', [MemberPortalController::class, 'documents'])->name('member.documents');
            Route::get('member/documents/{slug}/download', [MemberPortalController::class, 'download'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('member.download');
        });
        Route::get('verify/{number}/{check}', [MemberPortalController::class, 'verifyCard'])
            ->where(['number' => 'HPP-[0-9]{6}', 'check' => '[0-9a-f]{16}'])->middleware(['throttle:search', NoStore::class])->name('member.verify');
    });

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
        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('documents/{slug}', [DocumentController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('documents.show');
        // Counted, so never answered from the cache.
        Route::get('documents/{slug}/download', [DocumentController::class, 'download'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')
            ->withoutMiddleware(CachePublicPages::class)->name('documents.download');
        Route::get('notices', [DocumentController::class, 'notices'])->name('documents.notices');
        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/{slug}', [ProjectController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('projects.show');
        Route::get('achievements', [ProjectController::class, 'achievements'])->name('projects.achievements');
        Route::get('press', [PressController::class, 'press'])->name('press');
        Route::get('get-involved', [PressController::class, 'getInvolved'])->name('get-involved');
        Route::get('social', [SocialController::class, 'index'])->name('social');
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
