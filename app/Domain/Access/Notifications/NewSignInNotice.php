<?php

namespace App\Domain\Access\Notifications;

use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Notifications\Notifications\StaffNotice;
use App\Models\User;
use App\Services\Calendar\EthiopianCalendar;
use App\Support\UserAgent;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Support\Traits\Localizable;

/**
 * Someone signed in to the person's account from a network and a browser not
 * seen in the last 90 days (PLAN.md §11 login alerts, ADR-0038). Always sent
 * in the portal and by email: a security alert cannot be switched off. The
 * queued copy is encrypted because it carries the address.
 */
final class NewSignInNotice extends StaffNotice implements ShouldBeEncrypted
{
    use Localizable;

    public function __construct(
        public readonly string $ip,
        public readonly string $userAgent,
        public readonly string $at,
    ) {}

    public function kind(): NoticeKind
    {
        return NoticeKind::Security;
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return filled($notifiable->email) ? ['database', 'mail'] : ['database'];
    }

    protected function title(string $locale): string
    {
        return __('access.sign_in_alert.title', [], $locale);
    }

    protected function lines(string $locale): array
    {
        return [
            __('access.sign_in_alert.body', [
                'date' => app(EthiopianCalendar::class)->format(CarbonImmutable::parse($this->at), $locale, withTime: true),
                'device' => $this->withLocale($locale, fn (): string => UserAgent::describe($this->userAgent)),
                'ip' => $this->ip,
            ], $locale),
            __('access.sign_in_alert.advice', [], $locale),
        ];
    }

    protected function url(): string
    {
        return Filament::getPanel('admin')->getProfileUrl() ?? url('/admin');
    }

    protected function actionLabel(string $locale): string
    {
        return __('access.sign_in_alert.open', [], $locale);
    }

    protected function icon(): string
    {
        return 'heroicon-o-shield-exclamation';
    }
}
