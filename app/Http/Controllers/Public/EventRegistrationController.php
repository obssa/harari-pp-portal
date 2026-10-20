<?php

namespace App\Http\Controllers\Public;

use App\Domain\Events\Actions\CancelRegistration;
use App\Domain\Events\Actions\RegisterForEvent;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Models\EventTranslation;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Http\Controllers\Controller;
use App\Support\SpamGuard;
use App\Support\Turnstile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registering for an event, and the ticket that comes with it (ADR-0025).
 * These pages keep a session for the form protection and are never cached:
 * the ticket page is personal.
 */
final class EventRegistrationController extends Controller
{
    public function create(string $slug, Turnstile $turnstile): View
    {
        [$event, $translation] = $this->find($slug);

        return view('pages.events.register', [
            'event' => $event,
            'translation' => $translation,
            'state' => $event->registrationState(),
            'placesLeft' => $event->placesLeft(),
            'turnstileKey' => $turnstile->siteKey(),
            'alternates' => PublicDirectory::alternates($event, 'events.register', 'events.index'),
        ]);
    }

    public function store(Request $request, string $slug, RegisterForEvent $register, Turnstile $turnstile): RedirectResponse
    {
        [$event] = $this->find($slug);

        SpamGuard::check($request);
        $turnstile->check($request);

        $result = $register->handle($event, $request->only(['name', 'phone', 'email', 'consent']), app()->getLocale());

        if ($result->existing) {
            // Say no more than that the ticket was sent again to the details on file.
            return redirect()->route('events.register', ['slug' => $slug])->with('status', __('events.registration.again'));
        }

        return redirect()->route('events.ticket', ['token' => $result->registration->token])
            ->with('status', __("events.registration.done.{$result->registration->status->value}"));
    }

    public function ticket(string $token): Response
    {
        $registration = $this->registration($token);
        $event = $registration->event;
        $translation = PublicDirectory::shown($event, app()->getLocale());

        return response()->view('pages.events.ticket', [
            'registration' => $registration,
            'event' => $event,
            'translation' => $translation instanceof EventTranslation ? $translation : null,
            'token' => $token,
            'position' => $registration->waitlistPosition(),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function cancel(string $token, CancelRegistration $cancel): RedirectResponse
    {
        $registration = $this->registration($token);
        abort_if($registration->event->isPast(), 404);

        $cancel->handle($registration);

        return redirect()->route('events.ticket', ['token' => $token])->with('status', __('events.registration.cancelled'));
    }

    /**
     * @return array{0: Event, 1: EventTranslation}
     */
    private function find(string $slug): array
    {
        /** @var EventTranslation|null $translation */
        $translation = EventTranslation::query()->where('locale', app()->getLocale())->where('slug', $slug)->first();
        $event = $translation?->event()->with('translations')->first();
        abort_if($translation === null || $event === null || ! $translation->isLive() || ! $event->registration_enabled, 404);

        return [$event, $translation];
    }

    private function registration(string $token): EventRegistration
    {
        $registration = EventRegistration::findByToken($token);
        abort_if($registration === null || $registration->event()->first() === null, 404);
        $registration->load('event.translations');

        return $registration;
    }
}
