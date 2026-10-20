<?php

namespace App\Support;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as Generator;
use chillerlan\QRCode\QROptions;

/**
 * QR codes drawn on the server as SVG data addresses for an <img>: event
 * tickets (ADR-0025) and the Telegram channels on the social hub (ADR-0032).
 */
final class QrCode
{
    public static function dataUri(string $text): string
    {
        return (new Generator(new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'outputBase64' => true,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
        ])))->render($text);
    }
}
