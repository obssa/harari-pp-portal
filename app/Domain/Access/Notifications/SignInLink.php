<?php

namespace App\Domain\Access\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation / sign-in link for staff accounts, in the recipient's language.
 */
final class SignInLink extends Notification
{
    use Queueable;

    public function __construct(public readonly string $url, public readonly int $expiresInMinutes) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable instanceof User && $notifiable->locale ? $notifiable->locale : app()->getLocale();

        return (new MailMessage)
            ->subject(__('admin.mail.sign_in.subject', ['site' => __('site.name', [], $locale)], $locale))
            ->greeting(__('admin.mail.sign_in.greeting', [], $locale))
            ->line(__('admin.mail.sign_in.intro', [], $locale))
            ->action(__('admin.mail.sign_in.action', [], $locale), $this->url)
            ->line(__('admin.mail.sign_in.expiry', ['minutes' => $this->expiresInMinutes], $locale))
            ->line(__('admin.mail.sign_in.ignore', [], $locale));
    }
}
