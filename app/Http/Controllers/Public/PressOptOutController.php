<?php

namespace App\Http\Controllers\Public;

use App\Domain\Press\Models\MediaContact;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

/**
 * A journalist stops receiving press releases (ADR-0054): the link asks
 * first; confirming, or a mail app's one-click POST, opts them out. No
 * session, so mail apps can post without a form token.
 */
final class PressOptOutController extends Controller
{
    public function show(string $token): Response
    {
        $contact = $this->contact($token);

        return response()->view('pages.press.opt-out', ['token' => $token, 'done' => $contact->opted_out_at !== null])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function store(string $token): Response
    {
        $contact = $this->contact($token);
        if ($contact->opted_out_at === null) {
            $contact->forceFill(['opted_out_at' => CarbonImmutable::now()])->save();
        }

        return response()->view('pages.press.opt-out', ['token' => $token, 'done' => true])->header('X-Robots-Tag', 'noindex, nofollow');
    }

    private function contact(string $token): MediaContact
    {
        $contact = MediaContact::query()->where('token', $token)->first();
        abort_if($contact === null, 404);

        return $contact;
    }
}
