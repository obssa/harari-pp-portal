<?php

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Notifications\Support\NoticePreferences;
use App\Domain\Translation\Support\Locales;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A notice for staff (ADR-0036): shown in the bell and on the notifications
 * page, and sent by email, as each person chose for its kind, in their
 * admin language. Notices carry no personal details of the public.
 */
abstract class StaffNotice extends Notification implements ShouldQueue
{
    use Queueable;

    abstract public function kind(): NoticeKind;

    abstract protected function title(string $locale): string;

    /**
     * @return list<string>
     */
    abstract protected function lines(string $locale): array;

    abstract protected function url(): string;

    abstract protected function actionLabel(string $locale): string;

    protected function icon(): string
    {
        return 'heroicon-o-bell';
    }

    /** Notices of one group replace the unread one before them. */
    public function group(): ?string
    {
        return null;
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return NoticePreferences::via($notifiable, $this->kind());
    }

    public function toMail(User $notifiable): MailMessage
    {
        $locale = self::localeOf($notifiable);
        $message = (new MailMessage)->subject($this->title($locale));
        foreach ($this->lines($locale) as $line) {
            $message->line($line);
        }

        return $message
            ->action($this->actionLabel($locale), $this->url())
            ->line(__('notifications.mail.choices', [], $locale));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        $locale = self::localeOf($notifiable);

        return FilamentNotification::make()
            ->title($this->title($locale))
            ->body(implode(' ', $this->lines($locale)))
            ->icon($this->icon())
            ->actions([
                Action::make('open')
                    ->label($this->actionLabel($locale))
                    ->url($this->url())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage() + ['kind' => $this->kind()->value, 'group' => $this->group()];
    }

    protected static function localeOf(User $user): string
    {
        return Locales::isSupported($user->locale) ? (string) $user->locale : 'en';
    }

    /**
     * @param  array<string, string>  $texts  a text per language
     */
    protected static function pick(array $texts, string $locale): string
    {
        foreach (Locales::fallbackChain($locale) as $candidate) {
            if (filled($texts[$candidate] ?? null)) {
                return $texts[$candidate];
            }
        }

        return (string) (array_values(array_filter($texts))[0] ?? '');
    }

    /**
     * @param  callable(string): string  $text
     * @return array<string, string>
     */
    protected static function perLanguage(callable $text): array
    {
        $texts = [];
        foreach (Locales::all() as $locale) {
            $texts[$locale] = $text($locale);
        }

        return $texts;
    }
}
