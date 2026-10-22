<?php

namespace App\Domain\Homepage\Enums;

/**
 * How a carousel slide is laid out (ADR-0070).
 */
enum SlideLayout: string
{
    /** A photo across the whole slide, night blue over it, the text on top. */
    case Photo = 'photo';

    /** A white campaign banner: the heading, a slanted photo, a button row and a slogan. */
    case Banner = 'banner';

    /** Night blue with a framed picture beside a message and the name of who says it. */
    case Portrait = 'portrait';

    public function label(): string
    {
        return __("homepage.carousel.layout.{$this->value}");
    }

    public function description(): string
    {
        return __("homepage.carousel.layout.{$this->value}_hint");
    }

    /** Whether the slide needs a picture of its own (a photo slide without one shows Harar Gate). */
    public function needsImage(): bool
    {
        return $this !== self::Photo;
    }

    /** The image preset the layout shows its picture in. */
    public function preset(): string
    {
        return $this === self::Portrait ? 'square' : 'landscape';
    }
}
