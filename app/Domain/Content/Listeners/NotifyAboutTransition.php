<?php

namespace App\Domain\Content\Listeners;

use App\Domain\Access\Enums\UserStatus;
use App\Domain\Content\Events\ContentTransitioned;
use App\Domain\Content\Notifications\WorkflowNotification;
use App\Domain\Content\Workflow\Transition;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

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
            Transition::Submit => $this->withPermission('posts.review'),
            Transition::MarkReviewed => $this->withPermission('posts.publish'),
            Transition::SendBack, Transition::Publish, Transition::Schedule,
            Transition::Unpublish, Transition::Archive => $this->author($event),
            Transition::Reopen => new Collection,
        };

        $recipients = $recipients->reject(fn (User $user): bool => $user->id === $event->actor?->id
            || $user->status !== UserStatus::Active);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, WorkflowNotification::fromEvent($event));
        }
    }

    /**
     * Active staff holding the permission directly, through a role, or as Super Admin.
     *
     * @return Collection<int, User>
     */
    private function withPermission(string $permission): Collection
    {
        return User::query()
            ->where(fn ($query) => $query->permission($permission)->orWhereHas('roles', fn ($q) => $q->where('name', 'super_admin')))
            ->get();
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
