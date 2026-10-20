<?php

namespace App\Domain\Social\Enums;

/**
 * The networks the hub reads (PLAN.md §4: Facebook, Telegram, YouTube,
 * X, TikTok, Instagram).
 */
enum Platform: string
{
    case Telegram = 'telegram';
    case YouTube = 'youtube';
    case Facebook = 'facebook';
    case TikTok = 'tiktok';
    case Instagram = 'instagram';
    case X = 'x';

    public function label(): string
    {
        return __("social.platform.{$this->value}");
    }

    /** The public profile address for a handle. */
    public function profileUrl(string $handle): string
    {
        return match ($this) {
            self::Telegram => "https://t.me/{$handle}",
            self::YouTube => str_starts_with($handle, 'UC') ? "https://www.youtube.com/channel/{$handle}" : "https://www.youtube.com/@{$handle}",
            self::Facebook => "https://www.facebook.com/{$handle}",
            self::TikTok => "https://www.tiktok.com/@{$handle}",
            self::Instagram => "https://www.instagram.com/{$handle}",
            self::X => "https://x.com/{$handle}",
        };
    }

    /** How a handle must look on this network. */
    public function handlePattern(): string
    {
        return match ($this) {
            self::Telegram => '/^[A-Za-z][A-Za-z0-9_]{4,31}$/',
            self::YouTube => '/^(UC[A-Za-z0-9_-]{22}|[A-Za-z0-9._-]{3,30})$/',
            self::Facebook => '/^[A-Za-z0-9.]{5,80}$/',
            self::TikTok, self::Instagram, self::X => '/^[A-Za-z0-9._]{2,30}$/',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $platform) {
            $options[$platform->value] = $platform->label();
        }

        return $options;
    }
}
