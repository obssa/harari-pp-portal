<?php

namespace App\View\Components;

use App\Domain\Translation\Support\Locales;
use App\Http\Support\LocalizedUrls;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Links to the same page in every language, each named in its own script
 * (PLAN.md §12 UX rules).
 */
final class LanguageSwitcher extends Component
{
    /** @var list<array{locale: string, name: string, url: string, current: bool}> */
    public array $items = [];

    /**
     * @param  array<string, string>|null  $alternates  per-language URLs when slugs differ
     */
    public function __construct(?array $alternates = null)
    {
        $urls = $alternates ?? LocalizedUrls::forCurrentRoute();
        $current = app()->getLocale();

        foreach (Locales::all() as $locale) {
            if (! isset($urls[$locale])) {
                continue;
            }
            $this->items[] = [
                'locale' => $locale,
                'name' => Locales::nativeName($locale),
                'url' => $urls[$locale],
                'current' => $locale === $current,
            ];
        }
    }

    public function render(): View
    {
        return view('components.language-switcher');
    }
}
