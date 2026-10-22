<?php

namespace App\Http\Controllers\Public;

use App\Domain\Homepage\Models\HeroSlide;
use App\Domain\Translation\Support\Locales;
use App\Http\Controllers\Controller;
use App\Settings\CarouselSettings;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * One carousel slide on its own, as visitors would see it (ADR-0070), also
 * while it is off or scheduled, for whoever holds the signed link the admin
 * gives out. Never indexed or cached.
 */
final class SlidePreviewController extends Controller
{
    public function __invoke(string $slide, string $language): Response
    {
        abort_unless(Locales::isSupported($language), 404);
        app()->setLocale($language);
        URL::defaults(['locale' => $language]);

        $record = HeroSlide::query()->with('image')->find($slide);
        abort_unless($record instanceof HeroSlide, 404);

        return response()
            ->view('pages.home.slide-preview', [
                'slides' => [['kind' => 'slide', 'slide' => $record]],
                'settings' => CarouselSettings::current(),
                'slide' => $record,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'private, no-store');
    }
}
