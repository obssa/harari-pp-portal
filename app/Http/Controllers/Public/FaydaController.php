<?php

namespace App\Http\Controllers\Public;

use App\Domain\Membership\Actions\SignInWithFayda;
use App\Domain\Membership\Models\Member;
use App\Domain\Translation\Support\Locales;
use App\Http\Controllers\Controller;
use App\Services\Fayda\FaydaClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Member portal sign-in with Fayda (ADR-0058): to eSignet and back. The
 * state, nonce and PKCE verifier stay in the session for ten minutes; the
 * one return address has no language, so the language travels with them.
 */
final class FaydaController extends Controller
{
    private const FLOW = 'member.fayda';

    private const MINUTES = 10;

    public function start(Request $request, FaydaClient $fayda): RedirectResponse
    {
        abort_unless($fayda->isConfigured(), 404);
        $link = $request->routeIs('member.fayda.link');
        if ($link && ! Auth::guard('member')->user() instanceof Member) {
            return redirect()->route('member.sign-in');
        }

        $flow = [...FaydaClient::newFlow(), 'intent' => $link ? 'link' : 'sign-in', 'locale' => app()->getLocale(), 'at' => time()];
        try {
            $url = $fayda->authorizationUrl($flow, app()->getLocale());
        } catch (Throwable $e) {
            report($e);

            return redirect()->route($link ? 'member.home' : 'member.sign-in')->with('status', __('members.fayda.unavailable'));
        }

        $request->session()->put(self::FLOW, $flow);

        return redirect()->away($url);
    }

    public function callback(Request $request, FaydaClient $fayda, SignInWithFayda $signIn): RedirectResponse
    {
        $flow = $request->session()->pull(self::FLOW);
        $locale = is_array($flow) && Locales::isSupported($flow['locale'] ?? null) ? (string) $flow['locale'] : Locales::default();
        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);
        $fail = fn (string $key): RedirectResponse => redirect()->route('member.sign-in')->with('status', __($key));

        if (! is_array($flow) || ! is_string($request->query('state')) || ! hash_equals((string) $flow['state'], $request->query('state'))
            || time() - (int) $flow['at'] > self::MINUTES * 60) {
            return $fail('members.fayda.expired');
        }
        if ($request->query('error') !== null || ! is_string($request->query('code'))) {
            return $fail('members.fayda.cancelled');
        }

        try {
            $claims = $fayda->exchange($request->query('code'), $flow);
        } catch (Throwable $e) {
            report($e);

            return $fail('members.fayda.unavailable');
        }

        if ($flow['intent'] === 'link') {
            $member = Auth::guard('member')->user();
            if (! $member instanceof Member) {
                return $fail('members.fayda.expired');
            }
            try {
                $signIn->link($member, $claims['sub']);
            } catch (ValidationException) {
                return redirect()->route('member.home')->with('status', __('members.fayda.taken'));
            }

            return redirect()->route('member.home')->with('status', __('members.fayda.linked'));
        }

        $member = $signIn->member($claims);
        if ($member === null) {
            return $fail('members.fayda.no_member');
        }

        Auth::guard('member')->login($member);
        $request->session()->regenerate();
        activity('membership')->performedOn($member)->event('portal_sign_in')->log('Member signed in to the portal with Fayda');

        return redirect()->route('member.home');
    }

    public function unlink(Request $request, SignInWithFayda $signIn): RedirectResponse
    {
        $member = Auth::guard('member')->user();
        abort_unless($member instanceof Member, 403);
        $signIn->unlink($member);

        return redirect()->route('member.home')->with('status', __('members.fayda.unlinked'));
    }
}
