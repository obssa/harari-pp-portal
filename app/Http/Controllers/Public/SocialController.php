<?php

namespace App\Http\Controllers\Public;

use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Domain\Social\Enums\Platform;
use App\Domain\Social\Models\SocialSource;
use App\Domain\Social\Queries\SocialFeed;
use App\Http\Controllers\Controller;
use App\Support\QrCode;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * The social media hub (PLAN.md §4, ADR-0032): what the branch, its
 * offices and leagues post, with the accounts to follow.
 */
final class SocialController extends Controller
{
    public function index(Request $request): View
    {
        $locale = app()->getLocale();
        $platform = Platform::tryFrom((string) $request->query('platform', ''));
        $officeId = (string) $request->query('office', '') ?: null;
        $leagueId = (string) $request->query('league', '') ?: null;

        $sources = SocialSource::query()->where('enabled', true)->orderBy('name')->get();

        return view('pages.social', [
            'posts' => SocialFeed::query($platform, $officeId, $leagueId)->paginate(24)->withQueryString(),
            'platforms' => $sources->pluck('platform')->unique()->values(),
            'platform' => $platform,
            'offices' => Office::query()->whereIn('id', $sources->pluck('office_id')->filter())->with('translations')->get()
                ->mapWithKeys(fn (Office $o): array => [$o->id => $o->name($locale)])->all(),
            'leagues' => League::query()->whereIn('id', $sources->pluck('league_id')->filter())->with('translations')->get()
                ->mapWithKeys(fn (League $l): array => [$l->id => $l->name($locale)])->all(),
            'filters' => ['office' => $officeId, 'league' => $leagueId],
            'follow' => $sources->map(fn (SocialSource $source): array => [
                'source' => $source,
                'qr' => $source->platform === Platform::Telegram ? QrCode::dataUri($source->url()) : null,
            ]),
        ]);
    }
}
