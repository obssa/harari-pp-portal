<?php

namespace App\Http\Controllers\Public;

use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Office;
use App\Domain\Organisation\Queries\PublicDirectory;
use App\Domain\Volunteers\Actions\SaveVolunteer;
use App\Http\Controllers\Controller;
use App\Support\SpamGuard;
use App\Support\Turnstile;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The volunteer sign-up (PLAN.md §4 Get Involved, ADR-0048). The pages are
 * never cached or stored; the thank-you page needs the sign-up just made.
 */
final class VolunteerController extends Controller
{
    public function create(Turnstile $turnstile): View
    {
        $locale = app()->getLocale();

        return view('pages.volunteer.create', [
            'woredas' => PublicDirectory::visible(Office::query()->where('type', OfficeType::Woreda->value), $locale)
                ->with('translations')->orderBy('sort')->get()
                ->mapWithKeys(fn (Office $office): array => [$office->id => $office->name($locale)])->sort(),
            'turnstileKey' => $turnstile->siteKey(),
        ]);
    }

    public function store(Request $request, SaveVolunteer $volunteers, Turnstile $turnstile): RedirectResponse
    {
        SpamGuard::check($request);
        $turnstile->check($request);
        $volunteers->signUp($request->all(), app()->getLocale());

        return redirect()->route('volunteer.thanks')->with('volunteered', true);
    }

    public function thanks(Request $request): View|RedirectResponse
    {
        return $request->session()->get('volunteered') === true
            ? view('pages.volunteer.thanks')
            : redirect()->route('volunteer.create');
    }
}
