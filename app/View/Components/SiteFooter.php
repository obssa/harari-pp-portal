<?php

namespace App\View\Components;

use App\Domain\Content\Models\Menu;
use App\Services\Calendar\EthiopianCalendar;
use App\Settings\SiteSettings;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Throwable;

/**
 * The public footer (PLAN.md §3): about, contact and social blocks from the
 * site settings, in the page's language. Blocks without content are left out.
 */
final class SiteFooter extends Component
{
    /** Platform => display name; brand names are the same in every language. */
    public const PLATFORMS = [
        'facebook' => 'Facebook',
        'telegram' => 'Telegram',
        'youtube' => 'YouTube',
        'x' => 'X',
        'tiktok' => 'TikTok',
        'instagram' => 'Instagram',
    ];

    /** @var array<string, string> label key => value */
    public array $contact = [];

    public ?string $email = null;

    public ?string $phone = null;

    public ?string $hotline = null;

    /** @var array<string, array{name: string, url: string}> */
    public array $social = [];

    public string $years;

    /** @var list<array<string, mixed>> links from Admin → Menus → footer */
    public array $links = [];

    public function __construct()
    {
        $this->years = app(EthiopianCalendar::class)->years(now());
        $this->links = Menu::resolved('footer', app()->getLocale());

        // Settings load lazily; a database problem must not take the footer down.
        try {
            $this->load(app(SiteSettings::class));
        } catch (Throwable) {
            $this->contact = [];
            $this->email = $this->phone = $this->hotline = null;
            $this->social = [];
        }
    }

    private function load(SiteSettings $settings): void
    {
        $this->contact = array_filter([
            'footer.address' => (string) $settings->localized('address'),
            'footer.office_hours' => (string) $settings->localized('office_hours'),
        ], fn (string $value): bool => $value !== '');

        $this->email = filled($settings->contact_email) ? $settings->contact_email : null;
        $this->phone = filled($settings->contact_phone) ? $settings->contact_phone : null;
        $this->hotline = filled($settings->hotline) ? $settings->hotline : null;

        foreach (self::PLATFORMS as $platform => $name) {
            $url = $settings->social_links[$platform] ?? null;
            if (is_string($url) && str_starts_with($url, 'https://')) {
                $this->social[$platform] = ['name' => $name, 'url' => $url];
            }
        }
    }

    public function hasContact(): bool
    {
        return $this->contact !== [] || $this->email !== null || $this->phone !== null || $this->hotline !== null;
    }

    public function render(): View
    {
        return view('components.site-footer');
    }

    /** Digits and "+" only, for tel: links. */
    public function tel(string $number): string
    {
        return (string) preg_replace('/[^0-9+]/', '', $number);
    }
}
