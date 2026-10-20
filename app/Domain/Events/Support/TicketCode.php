<?php

namespace App\Domain\Events\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

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
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => true,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
        ]);

        return (new QRCode($options))->render(self::checkInUrl($token));
    }
}
