<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;
use Throwable;

/**
 * How the homepage carousel moves (ADR-0070): whether it turns by itself,
 * how long each slide stays, how it changes, which controls show, how tall
 * it is, and whether featured stories follow the slides.
 */
class CarouselSettings extends Settings
{
    public const TRANSITIONS = ['slide', 'focus'];

    public const HEIGHTS = ['compact', 'standard', 'tall'];

    public const MIN_INTERVAL = 3;

    public const MAX_INTERVAL = 30;

    public bool $autoplay;

    public int $interval;

    public string $transition;

    public bool $pause_on_hover;

    public bool $show_arrows;

    public bool $show_dots;

    public bool $show_progress;

    public string $height;

    public bool $include_stories;

    public static function group(): string
    {
        return 'carousel';
    }

    /**
     * The settings in force, with safe defaults while they cannot be read
     * (before the migrations have run).
     *
     * @return array{autoplay: bool, interval: int, transition: string, pause_on_hover: bool, show_arrows: bool, show_dots: bool, show_progress: bool, height: string, include_stories: bool}
     */
    public static function current(): array
    {
        try {
            $settings = app(self::class);

            return [
                'autoplay' => $settings->autoplay,
                'interval' => max(self::MIN_INTERVAL, min(self::MAX_INTERVAL, $settings->interval)),
                'transition' => in_array($settings->transition, self::TRANSITIONS, true) ? $settings->transition : 'slide',
                'pause_on_hover' => $settings->pause_on_hover,
                'show_arrows' => $settings->show_arrows,
                'show_dots' => $settings->show_dots,
                'show_progress' => $settings->show_progress,
                'height' => in_array($settings->height, self::HEIGHTS, true) ? $settings->height : 'standard',
                'include_stories' => $settings->include_stories,
            ];
        } catch (Throwable) {
            return ['autoplay' => true, 'interval' => 7, 'transition' => 'slide', 'pause_on_hover' => true, 'show_arrows' => true, 'show_dots' => true, 'show_progress' => true, 'height' => 'standard', 'include_stories' => true];
        }
    }
}
