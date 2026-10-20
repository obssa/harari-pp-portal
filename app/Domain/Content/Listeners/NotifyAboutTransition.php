<?php

namespace App\Domain\Content\Listeners;

use App\Domain\Content\Events\ContentTransitioned;
use App\Domain\Content\Notifications\WorkflowNotification;
use App\Domain\Content\Workflow\Transition;
use App\Domain\Notifications\Support\Recipients;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Who hears about a move (PLAN.md §7 editorial workflow):
 *  - submitted       → everyone who reviews posts;
 *  - reviewed        → everyone who publishes;
 *  - sent back       → the author;
 *  - published/scheduled/unpublished/archived → the author.
 * The person who made the move is never notified of it.
 */
final class NotifyAboutTransition
{
    public function handle(ContentTransitioned $event): void
    {
        $recipients = match ($event->transition) {
            Transition::Submit => Recipients::withPermission('posts.review'),
            Transition::MarkReviewed => Recipients::withPermission('posts.publish'),
            Transition::SendBack, Transition::Publish, Transition::Schedule,
            Transition::Unpublish, Transition::Archive => $this->author($event),
            Transition::Reopen => new Collection,
        };

        Recipients::notify($recipients, WorkflowNotification::fromEvent($event), except: $event->actor);
    }

    /**
     * @return Collection<int, User>
     */
    private function author(ContentTransitioned $event): Collection
    {
        $author = $event->post->created_by ? User::query()->find($event->post->created_by) : null;

        return new Collection($author ? [$author] : []);
    }
}
