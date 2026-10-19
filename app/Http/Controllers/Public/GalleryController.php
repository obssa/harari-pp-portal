<?php

namespace App\Http\Controllers\Public;

use App\Domain\Media\Models\Gallery;
use App\Domain\Media\Models\GalleryTranslation;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Photo galleries (PLAN.md §4 News & Media, ADR-0023): albums, and each
 * album's photos with a lightbox that works without JavaScript.
 */
final class GalleryController extends Controller
{
    public function index(): View
    {
        $galleries = PublicDirectory::visible(Gallery::query(), app()->getLocale())
            ->with('cover')
            ->withCount('items')
            ->orderByDesc('taken_on')
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('pages.gallery.index', ['galleries' => $galleries]);
    }

    public function show(string $slug): View
    {
        $locale = app()->getLocale();

        /** @var GalleryTranslation|null $translation */
        $translation = GalleryTranslation::query()->where('locale', $locale)->where('slug', $slug)->first();
        $gallery = $translation?->gallery()->with(['translations', 'cover', 'items.media', 'office.translations', 'league.translations'])->first();
        abort_if($translation === null || $gallery === null || ! $translation->isLive(), 404);

        // Photos removed from the library leave their albums too (foreign key cascade).
        $items = $gallery->items;
        $url = route('gallery.show', ['slug' => $translation->slug]);

        return view('pages.gallery.show', [
            'gallery' => $gallery,
            'translation' => $translation,
            'items' => $items,
            'office' => $gallery->office !== null ? PublicDirectory::shown($gallery->office, $locale) : null,
            'league' => $gallery->league !== null ? PublicDirectory::shown($gallery->league, $locale) : null,
            'alternates' => PublicDirectory::alternates($gallery, 'gallery.show', 'gallery.index'),
            'image' => PublicDirectory::shareImage($gallery->cover ?? $items->first()?->media, 'landscape', "gallery-{$gallery->id}-{$locale}", $translation->title, __('gallery.title')),
            'jsonLd' => [array_filter([
                '@type' => 'ImageGallery',
                '@id' => $url.'#gallery',
                'name' => $translation->title,
                'description' => $translation->summary,
                'url' => $url,
                'dateCreated' => $gallery->taken_on?->toDateString(),
                'numberOfItems' => $items->count(),
                'publisher' => ['@id' => url('/').'#organization'],
            ], fn (mixed $value): bool => $value !== null)],
        ]);
    }
}
