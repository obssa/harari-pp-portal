<?php

namespace App\Domain\Content\Events;

use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Domain\Content\Workflow\Transition;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A language of a post moved in the workflow (submitted, sent back,
 * reviewed, published, scheduled, unpublished, archived, reopened). The
 * actor is null when the scheduler published it.
 */
final class ContentTransitioned
{
    use Dispatchable;

    public function __construct(
        public readonly Post $post,
        public readonly PostTranslation $translation,
        public readonly Transition $transition,
        public readonly ?User $actor,
        public readonly ?string $comment,
    ) {}

    /** Name used for webhooks, e.g. "post.published". */
    public function webhookEvent(): string
    {
        return match ($this->transition) {
            Transition::Publish => 'post.published',
            Transition::Schedule => 'post.scheduled',
            Transition::Unpublish => 'post.unpublished',
            Transition::Archive => 'post.archived',
            default => 'post.'.$this->transition->value,
        };
    }
}
