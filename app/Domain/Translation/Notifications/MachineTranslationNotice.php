<?php

namespace App\Domain\Translation\Notifications;

use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Notifications\Notifications\StaffNotice;
use App\Domain\Translation\Support\Locales;
use App\Filament\Pages\ManageTranslations;

/**
 * The machine translation someone started in the background has finished
 * (ADR-0035, ADR-0036): how many strings wait for a check, how many are
 * still missing.
 */
final class MachineTranslationNotice extends StaffNotice
{
    public function __construct(
        public readonly string $language,
        public readonly int $translated,
        public readonly int $missing,
    ) {}

    public function kind(): NoticeKind
    {
        return NoticeKind::Imports;
    }

    protected function title(string $locale): string
    {
        return __('notifications.machine.title', ['language' => Locales::nativeName($this->language)], $locale);
    }

    protected function lines(string $locale): array
    {
        return [__('notifications.machine.body', ['count' => $this->translated, 'left' => $this->missing], $locale)];
    }

    protected function url(): string
    {
        return ManageTranslations::getUrl(['language' => $this->language], panel: 'admin');
    }

    protected function actionLabel(string $locale): string
    {
        return __('notifications.machine.open', [], $locale);
    }

    protected function icon(): string
    {
        return 'heroicon-o-sparkles';
    }
}
