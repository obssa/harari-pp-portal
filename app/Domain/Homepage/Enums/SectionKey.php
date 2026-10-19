<?php

namespace App\Domain\Homepage\Enums;

/**
 * The homepage sections the builder offers (PLAN.md §4 Homepage). Events,
 * projects, the social hub, video, gallery and newsletter sections join
 * when their modules exist.
 */
enum SectionKey: string
{
    case Ticker = 'ticker';
    case Hero = 'hero';
    case Leadership = 'leadership';
    case LatestNews = 'latest_news';
    case Press = 'press';
    case Counters = 'counters';
    case Values = 'values';
    case Leagues = 'leagues';
    case Partners = 'partners';
    case Gallery = 'gallery';

    public function label(?string $locale = null): string
    {
        return __("homepage.section.{$this->value}", [], $locale);
    }

    /** Whether the section starts switched on. */
    public function enabledByDefault(): bool
    {
        return ! in_array($this, [self::Ticker, self::Leadership, self::Counters, self::Partners], true);
    }

    /** Most posts (or other records) the section may pick or show. */
    public function maxItems(): int
    {
        return match ($this) {
            self::Ticker, self::Hero, self::Press => 5,
            self::LatestNews => 12,
            self::Counters, self::Values => 8,
            self::Leagues => 4,
            self::Partners => 12,
            self::Gallery => 8,
            self::Leadership => 1,
        };
    }

    /** How many items show when no limit is set. */
    public function defaultLimit(): int
    {
        return match ($this) {
            self::LatestNews => 6,
            self::Leagues => 2,
            default => $this->maxItems(),
        };
    }

    /** Sections where editors may hand-pick posts. */
    public function takesCuratedPosts(): bool
    {
        return in_array($this, [self::Ticker, self::Hero], true);
    }
}
