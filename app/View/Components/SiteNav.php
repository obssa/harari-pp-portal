<?php

namespace App\View\Components;

use App\Domain\Content\Models\Menu;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The main menu from Admin → Menus (header), in the page's language. Until
 * an editor builds it, Home and News are shown.
 */
final class SiteNav extends Component
{
    /** @var list<array<string, mixed>> */
    public array $items;

    public function __construct(public string $location = 'header')
    {
        $locale = app()->getLocale();
        $items = Menu::resolved($location, $locale);

        if ($items === [] && $location === 'header') {
            $items = [
                ['label' => __('nav.home'), 'url' => route('home'), 'external' => false, 'current' => 'home', 'children' => []],
                ['label' => __('nav.news'), 'url' => route('news.index'), 'external' => false, 'current' => 'news.*', 'children' => []],
            ];
        }

        $here = url()->current();
        $this->items = array_map(function (array $item) use ($here): array {
            $item['active'] = ($item['current'] !== null && request()->routeIs($item['current'])) || $item['url'] === $here;

            return $item;
        }, $items);
    }

    public function render(): View
    {
        return view('components.site-nav');
    }
}
