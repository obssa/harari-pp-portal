<?php

namespace App\Domain\Social\Notifications;

use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Notifications\Notifications\StaffNotice;
use App\Domain\Social\Models\SocialSource;
use App\Filament\Resources\SocialSources\SocialSourceResource;
use App\Services\Calendar\EthiopianCalendar;
use Carbon\CarbonImmutable;

/**
 * A Facebook page token expires soon, or no longer works (ADR-0038).
 */
final class TokenExpiringNotice extends StaffNotice
{
    public function __construct(
        public readonly string $sourceId,
        public readonly string $name,
        public readonly ?string $expiresAt,
    ) {}

    /** No date: expired or refused by Meta. */
    public static function for(SocialSource $source, ?CarbonImmutable $expiresAt): self
    {
        return new self((string) $source->id, $source->name ?: $source->handle, $expiresAt?->isFuture() ? $expiresAt->toIso8601String() : null);
    }

    public function kind(): NoticeKind
    {
        return NoticeKind::Sources;
    }

    public function group(): string
    {
        return "token:{$this->sourceId}";
    }

    protected function title(string $locale): string
    {
        return $this->expiresAt === null
            ? __('social.token.expired_title', ['name' => $this->name], $locale)
            : __('social.token.expiring_title', ['name' => $this->name], $locale);
    }

    protected function lines(string $locale): array
    {
        return [
            $this->expiresAt === null
                ? __('social.token.expired_body', [], $locale)
                : __('social.token.expiring_body', ['date' => app(EthiopianCalendar::class)->format(CarbonImmutable::parse($this->expiresAt), $locale)], $locale),
            __('social.token.renew', [], $locale),
        ];
    }

    protected function url(): string
    {
        return SocialSourceResource::getUrl(panel: 'admin');
    }

    protected function actionLabel(string $locale): string
    {
        return __('notifications.source.open', [], $locale);
    }

    protected function icon(): string
    {
        return 'heroicon-o-key';
    }
}
