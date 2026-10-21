<?php

namespace App\Http\Controllers\Public;

use App\Domain\Engagement\Actions\Subscribe;
use App\Domain\Engagement\Models\CampaignDelivery;
use App\Domain\Engagement\Models\Subscriber;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

/**
 * The unsubscribe link of every campaign message (ADR-0052). Opening it shows
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

    private function delivery(string $token): CampaignDelivery
    {
        $delivery = CampaignDelivery::query()->where('token', $token)->first();
        abort_if($delivery === null, 404);

        return $delivery;
    }
}
