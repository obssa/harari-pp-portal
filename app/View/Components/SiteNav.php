<?php

namespace App\View\Components;

use App\Domain\Content\Models\Menu;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\PageTranslation;
use App\Settings\DonationSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;
use Throwable;

/**
 * The main menu from Admin → Menus (header), in the page's language. Until
 * an editor builds one, the sections of the design (ADR-0062) are shown,
 * each pointing at the site's own listings.
 */
final class SiteNav extends Component
{
    /** @var list<array<string, mixed>> */
    public array $items;

    /**
     * @param  'bar'|'drawer'  $variant  the desktop bar with panels, or the phone menu behind a button
     */
    public function __construct(public string $location = 'header', public string $variant = 'bar')
    {
        $locale = app()->getLocale();
        $items = Menu::resolved($location, $locale);

        if ($items === [] && $location === 'header') {
            $items = self::defaultItems();
        }

        $here = url()->current();
        $this->items = array_map(function (array $item) use ($here): array {
            $item['active'] = self::isActive($item, $here);
            foreach ($item['children'] as $child) {
                $item['active'] = $item['active'] || self::isActive($child, $here);
            }

            return $item;
        }, $items);
    }

    public function render(): View
    {
        return view('components.site-nav');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function isActive(array $item, string $here): bool
    {
        return (is_string($item['current']) && request()->routeIs($item['current'])) || $item['url'] === $here;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function defaultItems(): array
    {
        $link = fn (string $label, string $url, ?string $current, ?string $hint = null): array => [
            'label' => __($label), 'url' => $url, 'external' => false, 'current' => $current, 'hint' => $hint === null ? null : __($hint), 'children' => [],
        ];
        $section = fn (string $label, string $desc, string $url, ?string $current, array $children): array => [
            ...$link($label, $url, $current), 'description' => __($desc), 'children' => $children,
        ];

        $getInvolved = [
            $link('nav.become_member', route('membership.apply'), 'membership.*', 'nav.become_member_hint'),
            $link('nav.volunteer', route('volunteer.create'), 'volunteer.*', 'nav.volunteer_hint'),
            $link('events.title', route('events.index'), 'events.*', 'nav.events_hint'),
            $link('nav.newsletter', route('newsletter.create'), 'newsletter.*', 'nav.newsletter_hint'),
        ];
        if (self::donationsOpen()) {
            $getInvolved[] = $link('donations.title', route('donations.create'), 'donations.*', 'nav.donate_hint');
        }
        $getInvolved[] = $link('nav.member_portal', route('member.sign-in'), 'member.*', 'nav.member_portal_hint');

        $about = self::aboutPages(app()->getLocale());

        return [
            $section('nav.about', 'nav.about_desc', $about[0]['url'] ?? route('structure'), null, [
                ...$about,
                $link('structure.title', route('structure'), 'structure', 'nav.structure_hint'),
                $link('leaders.title', route('leaders.index'), 'leaders.*', 'nav.leaders_hint'),
                $link('nav.branch_office', route('offices.index', ['type' => 'branch']), null, 'nav.branch_office_hint'),
            ]),
            $section('nav.media', 'nav.media_desc', route('news.index'), null, [
                $link('nav.news', route('news.index'), 'news.*', 'nav.news_hint'),
                $link('nav.press_releases', route('news.index', ['type' => 'press_release']), null, 'nav.press_releases_hint'),
                $link('nav.statements', route('news.index', ['type' => 'statement']), null, 'nav.statements_hint'),
                $link('nav.speeches', route('news.index', ['type' => 'speech']), null, 'nav.speeches_hint'),
                $link('gallery.title', route('gallery.index'), 'gallery.*', 'nav.gallery_hint'),
                $link('nav.videos', route('videos.index'), 'videos.*', 'nav.videos_hint'),
                $link('social.hub.title', route('social'), 'social', 'nav.social_hint'),
                $link('press.title', route('press'), 'press*', 'nav.press_hint'),
            ]),
            $section('nav.offices', 'nav.offices_desc', route('offices.index'), 'offices.*', [
                $link('nav.offices_woreda', route('offices.index', ['type' => 'woreda']), null, 'nav.offices_woreda_hint'),
                $link('nav.offices_kebele', route('offices.index', ['type' => 'kebele']), null, 'nav.offices_kebele_hint'),
                $link('nav.offices_sector', route('offices.index', ['type' => 'sector']), null, 'nav.offices_sector_hint'),
                $link('nav.offices_basic', route('offices.index', ['type' => 'basic_org']), null, 'nav.offices_basic_hint'),
            ]),
            $link('nav.leagues', route('leagues.index'), 'leagues.*'),
            $section('nav.programmes', 'nav.programmes_desc', route('projects.index'), null, [
                $link('projects.title', route('projects.index'), 'projects.index', 'nav.projects_hint'),
                $link('nav.achievements', route('projects.achievements'), 'projects.achievements', 'nav.achievements_hint'),
            ]),
            $section('nav.get_involved', 'nav.get_involved_desc', route('get-involved'), 'get-involved', $getInvolved),
            $section('nav.resources', 'nav.resources_desc', route('documents.index'), null, [
                $link('documents.title', route('documents.index'), 'documents.index', 'nav.documents_hint'),
                $link('nav.doc_programme', route('documents.index', ['type' => 'programme']), null, 'nav.doc_programme_hint'),
                $link('nav.doc_bylaws', route('documents.index', ['type' => 'bylaw']), null, 'nav.doc_bylaws_hint'),
                $link('nav.doc_policies', route('documents.index', ['type' => 'policy']), null, 'nav.doc_policies_hint'),
                $link('nav.doc_reports', route('documents.index', ['type' => 'report']), null, 'nav.doc_reports_hint'),
                $link('nav.notices', route('documents.notices'), 'documents.notices', 'nav.notices_hint'),
            ]),
            $link('nav.contact', route('contact'), 'contact'),
        ];
    }

    /**
     * The published "about" page and its published pages in a language
     * (ADR-0063), first in the About section; none until they exist.
     *
     * @return list<array<string, mixed>>
     */
    private static function aboutPages(string $locale): array
    {
        try {
            return Cache::remember("menu:about:{$locale}", 600, function () use ($locale): array {
                $translation = PageTranslation::query()->with('page')->where('locale', $locale)->where('slug', 'about')->first();
                $page = $translation?->page;
                if (! $page instanceof Page || $page->parent_id !== null || ! $translation->isLive()) {
                    return [];
                }

                $item = fn (PageTranslation $t, string $path): array => [
                    'label' => $t->title, 'url' => route('page.show', ['locale' => $locale, 'path' => $path]),
                    'external' => false, 'current' => null, 'hint' => $t->excerpt, 'children' => [],
                ];
                $items = [$item($translation, 'about')];
                foreach ($page->children()->with('translations')->orderBy('sort')->get() as $child) {
                    $childTranslation = $child->translation($locale, fallback: false);
                    if ($childTranslation instanceof PageTranslation && $childTranslation->isLive() && filled($childTranslation->slug)) {
                        $items[] = $item($childTranslation, "about/{$childTranslation->slug}");
                    }
                }

                return $items;
            });
        } catch (Throwable) {
            return [];
        }
    }

    private static function donationsOpen(): bool
    {
        try {
            return app(DonationSettings::class)->enabled;
        } catch (Throwable) {
            return false;
        }
    }
}
