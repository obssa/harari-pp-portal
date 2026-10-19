<?php

namespace App\View\Components;

use App\Settings\SiteSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\View\Component;
use Throwable;

/**
 * Search and sharing metadata for every public page (PLAN.md §11 SEO):
 * Open Graph and Twitter cards in the page's language, and JSON-LD — the
 * party as an organisation on every page plus whatever the page adds
 * (NewsArticle, BreadcrumbList ...).
 */
final class Seo extends Component
{
    /** @var list<array<string, mixed>> */
    public array $graph = [];

    public string $nonce;

    public string $ogLocale;

    /** @var list<string> */
    public array $ogAlternates = [];

    /**
     * @param  list<array<string, mixed>>  $jsonLd  extra JSON-LD nodes for this page
     */
    public function __construct(
        public string $title,
        public string $description,
        public ?string $image = null,
        public string $type = 'website',
        array $jsonLd = [],
    ) {
        $locale = app()->getLocale();
        $this->ogLocale = self::ogLocale($locale);
        foreach ((array) config('portal.locales') as $other) {
            if ($other !== $locale) {
                $this->ogAlternates[] = self::ogLocale((string) $other);
            }
        }

        $this->nonce = (string) Vite::cspNonce();
        $this->graph = [self::organisation(), [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'url' => route('home'),
            'name' => __('site.name'),
            'inLanguage' => $locale,
            'publisher' => ['@id' => url('/').'#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => route('search').'?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ], ...$jsonLd];
    }

    public function render(): View
    {
        return view('components.seo');
    }

    public function json(): string
    {
        return (string) json_encode(
            ['@context' => 'https://schema.org', '@graph' => $this->graph],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function organisation(): array
    {
        $node = [
            '@type' => 'PoliticalParty',
            '@id' => url('/').'#organization',
            'name' => __('site.name'),
            'alternateName' => __('site.office'),
            'url' => url('/'),
            'logo' => url('/favicon.svg'),
        ];

        try {
            $settings = app(SiteSettings::class);
            $sameAs = array_values(array_filter(
                array_map(fn (string $platform): ?string => $settings->social_links[$platform] ?? null, array_keys(SiteFooter::PLATFORMS)),
                fn (?string $url): bool => is_string($url) && str_starts_with($url, 'https://'),
            ));
            if ($sameAs !== []) {
                $node['sameAs'] = $sameAs;
            }
            if (filled($settings->contact_email)) {
                $node['email'] = $settings->contact_email;
            }
            if (filled($settings->contact_phone)) {
                $node['telephone'] = $settings->contact_phone;
            }
        } catch (Throwable) {
            // Settings unavailable: the basic organisation is enough.
        }

        return $node;
    }

    private static function ogLocale(string $locale): string
    {
        return match ($locale) {
            'am' => 'am_ET',
            'om' => 'om_ET',
            'har' => 'har_ET',
            default => 'en_US',
        };
    }
}
