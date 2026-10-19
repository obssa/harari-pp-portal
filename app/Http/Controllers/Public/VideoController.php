<?php

namespace App\Http\Controllers\Public;

use App\Domain\Content\Models\Category;
use App\Domain\Media\Models\Video;
use App\Domain\Media\Models\VideoTranslation;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Videos and the live page (PLAN.md §4 News & Media, ADR-0024). YouTube
 * videos play in the privacy-enhanced player on their own page; listings
 * only show pictures from our library.
 */
final class VideoController extends Controller
{
    public function index(Request $request): View
    {
        $locale = app()->getLocale();
        $playlists = Category::query()->where('type', Category::TYPE_VIDEO)->with('translations')->orderBy('sort')->get();
        $playlist = $playlists->first(fn (Category $c): bool => $c->translation()?->slug === $request->query('playlist'));

        $videos = PublicDirectory::visible(Video::query(), $locale)
            ->with('thumbnail')
            ->when($playlist, fn (Builder $q, Category $c) => $q->where('category_id', $c->id))
            ->orderByDesc('recorded_on')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        return view('pages.videos.index', [
            'videos' => $videos,
            'playlists' => $playlists,
            'playlist' => $playlist,
            'live' => PublicDirectory::visible(Video::query(), $locale)->liveNow()->exists(),
        ]);
    }

    public function show(string $slug): View
    {
        $locale = app()->getLocale();

        /** @var VideoTranslation|null $translation */
        $translation = VideoTranslation::query()->where('locale', $locale)->where('slug', $slug)->first();
        $video = $translation?->video()->with(['translations', 'thumbnail', 'category.translations', 'office.translations', 'league.translations'])->first();
        abort_if($translation === null || $video === null || ! $translation->isLive(), 404);

        $url = route('videos.show', ['slug' => $translation->slug]);
        $link = $video->link();

        return view('pages.videos.show', [
            'video' => $video,
            'translation' => $translation,
            'link' => $link,
            'office' => $video->office !== null ? PublicDirectory::shown($video->office, $locale) : null,
            'league' => $video->league !== null ? PublicDirectory::shown($video->league, $locale) : null,
            'alternates' => PublicDirectory::alternates($video, 'videos.show', 'videos.index'),
            'image' => PublicDirectory::shareImage($video->thumbnail, 'landscape', "video-{$video->id}-{$locale}", $translation->title, __('video.title')),
            'jsonLd' => [array_filter([
                '@type' => 'VideoObject',
                '@id' => $url.'#video',
                'name' => $translation->title,
                'description' => $translation->summary ?: $translation->title,
                'thumbnailUrl' => $video->thumbnail?->hasGeneratedConversion('landscape-960') ? $video->thumbnail->getUrl('landscape-960') : null,
                'uploadDate' => ($video->recorded_on ?? $translation->published_at)?->toIso8601String(),
                'embedUrl' => $link?->embedUrl(),
                'url' => $url,
                'publisher' => ['@id' => url('/').'#organization'],
            ], fn (mixed $value): bool => $value !== null)],
        ]);
    }

    public function live(): View
    {
        $locale = app()->getLocale();
        $base = fn (): Builder => PublicDirectory::visible(Video::query(), $locale)->with('thumbnail');

        return view('pages.videos.live', [
            'now' => $base()->liveNow()->orderByDesc('live_starts_at')->first(),
            'next' => $base()->liveSoon()->orderBy('live_starts_at')->first(),
            'recent' => $base()->orderByDesc('recorded_on')->orderByDesc('created_at')->limit(3)->get(),
        ]);
    }
}
