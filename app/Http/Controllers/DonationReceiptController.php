<?php

namespace App\Http\Controllers;

use App\Domain\Donations\Enums\DonationStatus;
use App\Domain\Donations\Models\Donation;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * A printable receipt for a recorded donation (ADR-0060), for staff who may
 * see donations. Printing is noted in the audit log.
 */
final class DonationReceiptController extends Controller
{
    public function __invoke(Donation $donation): Response
    {
        $user = Auth::guard('web')->user();
        abort_unless($user instanceof User && $user->can('view', $donation), 403);
        abort_unless($donation->status === DonationStatus::Received && $donation->receipt_no !== null, 404);

        // In the donor's language: the receipt is for them.
        app()->setLocale($donation->locale);

        activity('donations')->performedOn($donation)->causedBy($user)->event('receipt_printed')->log('Donation receipt printed');

        return response()->view('donations.receipt', ['donation' => $donation->load('recorder')])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex');
    }
}
