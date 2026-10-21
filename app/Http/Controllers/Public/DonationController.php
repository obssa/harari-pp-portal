<?php

namespace App\Http\Controllers\Public;

use App\Domain\Donations\Actions\PledgeDonation;
use App\Domain\Donations\Models\Donation;
use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentGateway;
use App\Settings\DonationSettings;
use App\Support\SpamGuard;
use App\Support\Turnstile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The donate page (ADR-0060): there only while a Super Admin has switched
 * donations on. A pledge leads to the bank-transfer instructions with its
 * reference; nothing here is cached.
 */
final class DonationController extends Controller
{
    private const PLEDGED = 'donation.pledged';

    public function create(DonationSettings $settings, Turnstile $turnstile): View
    {
        abort_unless($settings->enabled, 404);

        return view('pages.donations.create', [
            'settings' => $settings,
            'intro' => $this->text($settings->intro),
            'turnstileKey' => $turnstile->siteKey(),
        ]);
    }

    public function store(Request $request, PledgeDonation $pledge, PaymentGateway $gateway, Turnstile $turnstile): RedirectResponse
    {
        SpamGuard::check($request);
        $turnstile->check($request);

        $donation = $pledge->handle($request->all(), app()->getLocale());
        $online = $gateway->isOnline() ? $gateway->start($donation) : null;
        if ($online !== null) {
            return redirect()->away($online);
        }

        $request->session()->put(self::PLEDGED, $donation->id);

        return redirect()->route('donations.thanks');
    }

    public function thanks(Request $request, DonationSettings $settings): View|RedirectResponse
    {
        $donation = Donation::query()->find($request->session()->get(self::PLEDGED));
        if (! $donation instanceof Donation || ! $settings->enabled) {
            return redirect()->route('home');
        }

        return view('pages.donations.thanks', [
            'donation' => $donation,
            'accounts' => $settings->accounts,
            'instructions' => $this->text($settings->instructions),
        ]);
    }

    /**
     * The text in the page language, else its fallbacks, else English.
     *
     * @param  array<string, string>  $texts
     */
    private function text(array $texts): ?string
    {
        foreach ([app()->getLocale(), ...(array) config('portal.content_fallbacks.'.app()->getLocale(), []), 'en'] as $locale) {
            if (filled($texts[$locale] ?? null)) {
                return $texts[$locale];
            }
        }

        return null;
    }
}
