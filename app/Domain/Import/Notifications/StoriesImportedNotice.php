<?php

namespace App\Domain\Import\Notifications;

use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Notifications\Notifications\StaffNotice;
use App\Filament\Resources\Posts\PostResource;

/**
 * New stories from the public channels wait as drafts (ADR-0022, ADR-0036):
 * one notice per import run that brought any.
 */
final class StoriesImportedNotice extends StaffNotice
{
    public function __construct(public readonly int $count) {}

    public function kind(): NoticeKind
    {
        return NoticeKind::Imports;
    }

    protected function title(string $locale): string
    {
        return __('notifications.import.title', [], $locale);
    }

    protected function lines(string $locale): array
    {
        return [__('notifications.import.body', ['count' => $this->count], $locale)];
    }

    protected function url(): string
    {
        return PostResource::getUrl('index', panel: 'admin');
    }

    protected function actionLabel(string $locale): string
    {
        return __('notifications.import.open', [], $locale);
    }

    protected function icon(): string
    {
        return 'heroicon-o-arrow-down-tray';
    }
}
