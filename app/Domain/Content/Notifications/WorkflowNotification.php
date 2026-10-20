<?php

namespace App\Domain\Content\Notifications;

use App\Domain\Content\Events\ContentTransitioned;
use App\Domain\Notifications\Enums\NoticeKind;
use App\Domain\Notifications\Notifications\StaffNotice;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\Posts\PostResource;

/**
 * Tells staff that a post needs them (submitted, sent back, reviewed) or
 * that their post went live (PLAN.md §7: in-app and email notifications),
 * in each person's admin language.
 */
final class WorkflowNotification extends StaffNotice
{
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

    public function kind(): NoticeKind
    {
        return NoticeKind::Workflow;
    }

    protected function title(string $locale): string
    {
        return __("content.notification.{$this->transition}", [], $locale);
    }

    protected function lines(string $locale): array
    {
        $lines = [__('content.notification.summary', [
            'title' => $this->title,
            'language' => Locales::nativeName($this->language),
            'name' => $this->actorName ?? __('content.notification.system', [], $locale),
        ], $locale)];

        if (filled($this->comment)) {
            $lines[] = __('content.notification.comment', ['comment' => $this->comment], $locale);
        }

        return $lines;
    }

    protected function url(): string
    {
        return PostResource::getUrl('edit', ['record' => $this->postId], panel: 'admin');
    }

    protected function actionLabel(string $locale): string
    {
        return __('content.notification.open', [], $locale);
    }

    protected function icon(): string
    {
        return 'heroicon-o-newspaper';
    }
}
