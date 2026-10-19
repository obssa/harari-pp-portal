<?php

namespace App\Domain\Media\Support;

use App\Domain\Media\Exceptions\RejectedUpload;
use DOMDocument;
use Rhukster\DomSanitizer\DOMSanitizer;

/**
 * Cleans uploaded SVG files (PLAN.md §11): an allow-list of SVG elements and
 * attributes (rhukster/dom-sanitizer, MIT), no DOCTYPE or entities, no
 * scripts, event handlers, external references, <use> or <foreignObject>.
 * The result is checked again and refused if anything active survived.
 */
final class SvgSanitizer
{
    private const MAX_BYTES = 2 * 1024 * 1024;

    /** Patterns that must never appear in a stored SVG. */
    private const FORBIDDEN = [
        '/<\s*script/i',
        '/<\s*foreignobject/i',
        '/\son[a-z]+\s*=/i',
        '/javascript\s*:/i',
        '/<!ENTITY/i',
        '/<!DOCTYPE/i',
        '/<\?php/i',
    ];

    public function sanitize(string $svg): string
    {
        if (strlen($svg) > self::MAX_BYTES) {
            throw RejectedUpload::because('too_large');
        }

        $clean = (new DOMSanitizer(DOMSanitizer::SVG))->sanitize($svg, [
            'remove-namespaces' => false,
            'compress-output' => false,
        ]);

        $clean = $this->withSvgRoot($clean);

        foreach (self::FORBIDDEN as $pattern) {
            if (preg_match($pattern, $clean) === 1) {
                throw RejectedUpload::because('unsafe_svg');
            }
        }

        return $clean;
    }

    /**
     * The document must be a single <svg> element with the SVG namespace, or
     * browsers will not show it as an image.
     */
    private function withSvgRoot(string $clean): string
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $clean !== '' && $document->loadXML($clean, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $loaded ? $document->documentElement : null;
        if ($root === null || strtolower($root->localName) !== 'svg') {
            throw RejectedUpload::because('unsafe_svg');
        }

        if (! $root->hasAttribute('xmlns')) {
            $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
        }

        return (string) $document->saveXML($root);
    }
}
