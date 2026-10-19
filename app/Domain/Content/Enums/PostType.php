<?php

namespace App\Domain\Content\Enums;

/**
 * Kinds of post (PLAN.md §4 News & Media, §10).
 */
enum PostType: string
{
    case News = 'news';
    case PressRelease = 'press_release';
    case Statement = 'statement';
    case Speech = 'speech';
    case Announcement = 'announcement';

    public function label(?string $locale = null): string
    {
        return __("content.post_type.{$this->value}", [], $locale);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
