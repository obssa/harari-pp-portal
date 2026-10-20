<?php

namespace App\Domain\Notifications\Notifications;

use App\Domain\Import\Models\ContentSource;
use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Social\Models\SocialSource;
use App\Filament\Resources\ContentSources\ContentSourceResource;
use App\Filament\Resources\SocialSources\SocialSourceResource;
use Illuminate\Support\Str;

/**
 * A social media account or an import channel that was read fine can no
 * longer be read (ADR-0036). Sent once when it starts failing, not on every
 * attempt; it is read again on its usual schedule.
 */
final class SourceFailingNotice extends StaffNotice
{
    public const SOCIAL = 'social';

    public const IMPORT = 'import';

    public function __construct(
        public readonly string $type,
        public readonly string $sourceId,
        public readonly string $name,
        public readonly string $error,
    ) {}

    public static function social(SocialSource $source, string $error): self
    {
        return new self(self::SOCIAL, (string) $source->id, $source->name ?: $source->handle, $error);
    }

    public static function import(ContentSource $source, string $error): self
    {
        return new self(self::IMPORT, (string) $source->id, $source->name ?: $source->handle, $error);
    }

    public function kind(): NoticeKind
    {
        return NoticeKind::Sources;
    }

    public function group(): string
    {
        return "source:{$this->type}:{$this->sourceId}";
    }

    protected function title(string $locale): string
    {
        return __('notifications.source.title', ['name' => $this->name], $locale);
    }

    protected function lines(string $locale): array
    {
        return [
            __('notifications.source.body', ['error' => Str::limit($this->error, 300)], $locale),
            __('notifications.source.retry', [], $locale),
        ];
    }

    protected function url(): string
    {
        return $this->type === self::SOCIAL
            ? SocialSourceResource::getUrl(panel: 'admin')
            : ContentSourceResource::getUrl(panel: 'admin');
    }

    protected function actionLabel(string $locale): string
    {
        return __('notifications.source.open', [], $locale);
    }

    protected function icon(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }
}
