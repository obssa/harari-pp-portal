<?php

namespace App\Domain\Content\Workflow;

use App\Domain\Content\Enums\ContentStatus;

/**
 * The moves a language of a post can make (PLAN.md §7 editorial workflow,
 * ADR-0013). Each names the statuses it starts from, where it ends and the
 * permission it needs.
 */
enum Transition: string
{
    case Submit = 'submit';
    case SendBack = 'send_back';
    case MarkReviewed = 'mark_reviewed';
    case Publish = 'publish';
    case Schedule = 'schedule';
    case Unpublish = 'unpublish';
    case Archive = 'archive';
    case Reopen = 'reopen';

    /**
     * @return list<ContentStatus>
     */
    public function sources(): array
    {
        return match ($this) {
            self::Submit => [ContentStatus::Draft, ContentStatus::Translating],
            self::SendBack, self::MarkReviewed => [ContentStatus::InReview],
            self::Publish => [ContentStatus::Draft, ContentStatus::InReview, ContentStatus::Translating, ContentStatus::Scheduled],
            self::Schedule => [ContentStatus::Draft, ContentStatus::InReview, ContentStatus::Translating],
            self::Unpublish => [ContentStatus::Published, ContentStatus::Scheduled],
            self::Archive => [ContentStatus::Published],
            self::Reopen => [ContentStatus::Archived],
        };
    }

    public function to(): ContentStatus
    {
        return match ($this) {
            self::Submit, self::MarkReviewed => ContentStatus::InReview,
            self::SendBack, self::Unpublish, self::Reopen => ContentStatus::Draft,
            self::Publish => ContentStatus::Published,
            self::Schedule => ContentStatus::Scheduled,
            self::Archive => ContentStatus::Archived,
        };
    }

    /** Permission needed besides being allowed to edit the post (none for submit). */
    public function permission(): ?string
    {
        return match ($this) {
            self::Submit => null,
            self::SendBack, self::MarkReviewed => 'posts.review',
            self::Publish, self::Schedule, self::Unpublish, self::Archive, self::Reopen => 'posts.publish',
        };
    }

    public function needsComment(): bool
    {
        return $this === self::SendBack;
    }

    public function label(): string
    {
        return __("content.transition.{$this->value}");
    }

    public function allowedFrom(ContentStatus $status): bool
    {
        return in_array($status, $this->sources(), true);
    }
}
