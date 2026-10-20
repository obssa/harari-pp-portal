<?php

namespace App\Domain\Health\Notifications;

use App\Domain\Health\HealthChecks;
use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Notifications\Notifications\StaffNotice;
use App\Filament\Pages\SystemHealth;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Localizable;

/**
 * A health check of the portal started failing (ADR-0037). Sent when it
 * turns from passing to failing, not on every run.
 */
final class HealthFailingNotice extends StaffNotice
{
    use Localizable;

    public function __construct(
        public readonly string $check,
        public readonly string $message,
    ) {}

    public function kind(): NoticeKind
    {
        return NoticeKind::Health;
    }

    public function group(): string
    {
        return "health:{$this->check}";
    }

    protected function title(string $locale): string
    {
        return __('health.notice.title', ['check' => $this->withLocale($locale, fn (): string => HealthChecks::label($this->check))], $locale);
    }

    protected function lines(string $locale): array
    {
        return array_values(array_filter([
            filled($this->message) ? Str::limit($this->message, 300) : null,
            __('health.notice.body', [], $locale),
        ]));
    }

    protected function url(): string
    {
        return SystemHealth::getUrl(panel: 'admin');
    }

    protected function actionLabel(string $locale): string
    {
        return __('health.notice.open', [], $locale);
    }

    protected function icon(): string
    {
        return 'heroicon-o-heart';
    }
}
