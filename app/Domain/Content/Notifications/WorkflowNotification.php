<?php

namespace App\Domain\Content\Notifications;

use App\Domain\Content\Events\ContentTransitioned;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\Posts\PostResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells staff that a post needs them (submitted, sent back, reviewed) or
 * that their post went live (PLAN.md §7: in-app and email notifications),
 * in each person's admin language.
 */
final class WorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $postId,
        public readonly string $title,
        public readonly string $language,
        public readonly string $transition,
        public readonly ?string $actorName,
        public readonly ?string $comment,
    ) {}

    public static function fromEvent(ContentTransitioned $event): self
    {
        return new self(
            $event->post->id,
            $event->translation->title,
            $event->translation->locale,
            $event->transition->value,
            $event->actor?->name,
            $event->comment,
        );
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? 'en';

        $message = (new MailMessage)
            ->subject($this->headline($locale))
            ->line($this->summary($locale))
            ->action(__('content.notification.open', [], $locale), $this->url());

        if (filled($this->comment)) {
            $message->line(__('content.notification.comment', ['comment' => $this->comment], $locale));
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        $locale = $notifiable->locale ?? 'en';

        return FilamentNotification::make()
            ->title($this->headline($locale))
            ->body($this->summary($locale).(filled($this->comment) ? "\n".$this->comment : ''))
            ->icon('heroicon-o-newspaper')
            ->actions([
                Action::make('open')
                    ->label(__('content.notification.open', [], $locale))
                    ->url($this->url())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    private function headline(string $locale): string
    {
        return __("content.notification.{$this->transition}", [], $locale);
    }

    private function summary(string $locale): string
    {
        return __('content.notification.summary', [
            'title' => $this->title,
            'language' => Locales::nativeName($this->language),
            'name' => $this->actorName ?? __('content.notification.system', [], $locale),
        ], $locale);
    }

    private function url(): string
    {
        return PostResource::getUrl('edit', ['record' => $this->postId], panel: 'admin');
    }
}
