<?php

namespace App\Domain\Events\Support;

use App\Support\QrCode;

/**
 * The QR code on a ticket (ADR-0025). It opens the staff check-in screen
 * for that ticket; anyone else who scans it only reaches the admin sign-in.
 */
final class TicketCode
{
    public static function checkInUrl(string $token): string
    {
        return route('filament.admin.pages.check-in', ['code' => $token]);
    }

    /** The QR code as an SVG data address for an <img>. */
    public static function image(string $token): string
    {
        return QrCode::dataUri(self::checkInUrl($token));
    }
}
