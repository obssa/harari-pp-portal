<?php

namespace App\Http\Controllers\Public;

use App\Domain\Engagement\Actions\Subscribe;
use App\Domain\Engagement\Mail\CampaignMail;
use App\Domain\Engagement\Models\CampaignDelivery;
use App\Domain\Engagement\Models\Subscriber;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * The links of campaign messages (ADR-0052, ADR-0053): counted clicks and
 * opens, and the unsubscribe link. Opening the unsubscribe link shows
 * a button; pressing it, or a mail app's one-click POST, stops campaigns for
 * that person: a subscriber unsubscribes, a member or volunteer opts out.
 * No session, so mail apps can post without a form token.
 */
final class CampaignLinkController extends Controller
{
    public function show(string $token): Response
    {
        $delivery = $this->delivery($token);

        return response()->view('pages.campaigns.unsubscribe', ['token' => $token, 'done' => $delivery->unsubscribed_at !== null])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function unsubscribe(string $token, Subscribe $subscribe): Response
    {
        $delivery = $this->delivery($token);
        $person = $delivery->recipient();
        if ($person instanceof Subscriber) {
            $subscribe->unsubscribe($person);
        } elseif ($person !== null && $person->getAttribute('campaigns_opt_out_at') === null) {
            $person->forceFill(['campaigns_opt_out_at' => CarbonImmutable::now()])->saveQuietly();
        }
        $delivery->forceFill(['unsubscribed_at' => $delivery->unsubscribed_at ?? CarbonImmutable::now()])->save();

        return response()->view('pages.campaigns.unsubscribe', ['token' => $token, 'done' => true])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * A link in a campaign email: counted, then on to the address the campaign's
     * own text gave at that position, so it cannot send anyone elsewhere.
     */
    public function click(string $token, int $link): RedirectResponse
    {
        $delivery = $this->delivery($token);
        $campaign = $delivery->campaign;
        $language = $campaign->languageFor($delivery->locale);
        $url = $language === null ? null : (CampaignMail::links($campaign, $language)[$link] ?? null);
        abort_if($url === null, 404);

        $delivery->forceFill(['clicks' => $delivery->clicks + 1, 'clicked_at' => $delivery->clicked_at ?? CarbonImmutable::now()])->save();

        return redirect()->away($url)->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /** The open image, only in emails sent while open counting was on. */
    public function open(string $token): Response
    {
        $delivery = CampaignDelivery::query()->where('token', $token)->first();
        if ($delivery !== null && $delivery->opened_at === null) {
            $delivery->forceFill(['opened_at' => CarbonImmutable::now()])->save();
        }

        return response((string) base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function delivery(string $token): CampaignDelivery
    {
        $delivery = CampaignDelivery::query()->where('token', $token)->first();
        abort_if($delivery === null, 404);

        return $delivery;
    }
}
