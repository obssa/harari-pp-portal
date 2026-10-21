<?php

namespace App\Http\Controllers\Public;

use App\Domain\Engagement\Actions\Subscribe;
use App\Domain\Engagement\Models\Subscriber;
use App\Http\Controllers\Controller;
use App\Support\PhoneNumber;
use App\Support\SpamGuard;
use App\Support\Turnstile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Newsletter and SMS sign-up with double opt-in, and the preference centre
 * behind a private link (PLAN.md §2, §4; ADR-0051). None of these pages is
 * cached or stored.
 */
final class NewsletterController extends Controller
{
    private const PENDING = 'newsletter.pending';

    public function create(Request $request, Turnstile $turnstile): View
    {
        return view('pages.newsletter.create', [
            'turnstileKey' => $turnstile->siteKey(),
            // The footer's small form sends the address here to be completed.
            'email' => mb_substr((string) $request->query('email', ''), 0, 190),
        ]);
    }

    public function store(Request $request, Subscribe $subscribe, Turnstile $turnstile): RedirectResponse
    {
        SpamGuard::check($request);
        $turnstile->check($request);
        $result = $subscribe->handle($request->all(), app()->getLocale());

        $request->session()->put(self::PENDING, [
            'id' => $result['subscriber']->id,
            'email' => filled($request->input('email')) && in_array('email', (array) $request->input('channels', []), true),
            'phone' => $result['code_sent'] ? PhoneNumber::mask((string) $result['subscriber']->phone) : null,
        ]);

        return redirect()->route('newsletter.check');
    }

    public function check(Request $request): View|RedirectResponse
    {
        $pending = $request->session()->get(self::PENDING);
        if (! is_array($pending)) {
            return redirect()->route('newsletter.create');
        }

        return view('pages.newsletter.check', ['email' => (bool) ($pending['email'] ?? false), 'phone' => $pending['phone'] ?? null]);
    }

    public function verify(Request $request, Subscribe $subscribe): RedirectResponse
    {
        $pending = $request->session()->get(self::PENDING);
        $subscriber = is_array($pending) ? Subscriber::query()->whereNull('erased_at')->find($pending['id'] ?? null) : null;
        if (! $subscriber instanceof Subscriber) {
            return redirect()->route('newsletter.create');
        }
        $subscribe->verifyCode($subscriber, (string) $request->input('code', ''));
        $request->session()->forget(self::PENDING);

        return redirect()->route('newsletter.preferences', ['token' => $subscriber->token])->with('status', __('newsletter.confirmed'));
    }

    public function confirm(string $token, Subscribe $subscribe): RedirectResponse
    {
        $subscriber = Subscriber::byToken($token);
        abort_if($subscriber === null, 404);
        $subscribe->confirmEmail($subscriber);

        return redirect()->route('newsletter.preferences', ['token' => $token])->with('status', __('newsletter.confirmed'));
    }

    public function preferences(string $token): Response
    {
        $subscriber = Subscriber::byToken($token);
        abort_if($subscriber === null, 404);

        return response()->view('pages.newsletter.preferences', ['subscriber' => $subscriber, 'token' => $token])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function update(Request $request, string $token, Subscribe $subscribe): RedirectResponse
    {
        $subscriber = Subscriber::byToken($token);
        abort_if($subscriber === null, 404);
        if ($request->boolean('unsubscribe')) {
            $subscribe->unsubscribe($subscriber);
        } else {
            $subscribe->updatePreferences($subscriber, $request->all());
        }

        return redirect()->route('newsletter.preferences', ['token' => $token])
            ->with('status', $subscriber->unsubscribed_at !== null ? __('newsletter.unsubscribed') : __('newsletter.saved'));
    }

    /**
     * The mail app's own unsubscribe button (RFC 8058 one-click): a POST without
     * session or form token, so it lives outside the session routes. Links in
     * the emails open the preference centre instead, so a link scanner never
     * unsubscribes anyone.
     */
    public function unsubscribe(string $token, Subscribe $subscribe): Response
    {
        $subscriber = Subscriber::byToken($token);
        abort_if($subscriber === null, 404);
        $subscribe->unsubscribe($subscriber);

        return response()->view('pages.newsletter.unsubscribed')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
