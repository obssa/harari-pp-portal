<?php

namespace App\Domain\Content\Workflow;

use App\Domain\Content\Actions\RecordRevision;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Events\ContentTransitioned;
use App\Domain\Content\Models\Comment;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\PostTranslation;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moves one language of a post through the editorial workflow (PLAN.md §7,
 * ADR-0013): checks the move is allowed from the current status and that the
 * person may make it, stores comments, keeps a version, writes the audit log
 * and announces the move (notifications, webhooks).
 */
final class ContentWorkflow
{
    public function __construct(private readonly RecordRevision $revisions) {}

    /**
     * Moves this person may make on this language now.
     *
     * @return list<Transition>
     */
    public function available(Post $post, string $locale, User $actor): array
    {
        $translation = $post->translation($locale, fallback: false);
        if (! $translation instanceof PostTranslation) {
            return [];
        }

        return array_values(array_filter(
            Transition::cases(),
            fn (Transition $transition): bool => $transition->allowedFrom($translation->status)
                && $this->permitted($post, $transition, $actor),
        ));
    }

    public function apply(
        Post $post,
        string $locale,
        Transition $transition,
        User $actor,
        ?string $comment = null,
        ?DateTimeInterface $at = null,
    ): PostTranslation {
        $translation = $post->translation($locale, fallback: false);
        if (! $translation instanceof PostTranslation) {
            throw ValidationException::withMessages(['locale' => __('content.workflow.no_translation')]);
        }

        if (! $transition->allowedFrom($translation->status)) {
            throw ValidationException::withMessages(['transition' => __('content.workflow.not_allowed', [
                'transition' => $transition->label(),
                'status' => $translation->status->label(),
            ])]);
        }

        if (! $this->permitted($post, $transition, $actor)) {
            throw ValidationException::withMessages(['transition' => __('content.workflow.no_permission')]);
        }

        $comment = $comment !== null ? trim($comment) : null;
        if ($transition->needsComment() && ($comment === null || $comment === '')) {
            throw ValidationException::withMessages(['comment' => __('content.workflow.comment_required')]);
        }

        $when = $at !== null ? CarbonImmutable::instance($at) : null;
        if ($transition === Transition::Schedule && ($when === null || $when->isPast())) {
            throw ValidationException::withMessages(['at' => __('content.workflow.schedule_future')]);
        }

        $from = $translation->status;

        DB::transaction(function () use ($post, $translation, $transition, $actor, $comment, $when): void {
            $translation->status = $transition->to();

            match ($transition) {
                Transition::Publish => $translation->published_at = $when
                    ?? ($translation->published_at !== null && $translation->published_at->isPast() ? $translation->published_at : CarbonImmutable::now()),
                Transition::Schedule => $translation->published_at = $when,
                Transition::MarkReviewed => [$translation->reviewed_by, $translation->reviewed_at] = [$actor->id, CarbonImmutable::now()],
                default => null,
            };

            $translation->save();

            if ($comment !== null && $comment !== '') {
                $note = new Comment([
                    'locale' => $translation->locale,
                    'kind' => $transition === Transition::SendBack ? Comment::KIND_SEND_BACK : Comment::KIND_COMMENT,
                    'body' => $comment,
                ]);
                $note->commentable()->associate($post);
                $note->author()->associate($actor);
                $note->save();
            }

            $this->revisions->handle($post, $translation, $transition->label());
        });

        activity('workflow')
            ->performedOn($post)
            ->causedBy($actor)
            ->event($transition->value)
            ->withProperties([
                'locale' => $translation->locale,
                'from' => $from->value,
                'to' => $translation->status->value,
                'comment' => $comment,
            ])
            ->log("post.{$transition->value}");

        ContentTransitioned::dispatch($post, $translation, $transition, $actor, $comment);

        return $translation;
    }

    /**
     * Publishes every scheduled language whose time has come (run every minute).
     */
    public function publishDue(): int
    {
        $due = PostTranslation::query()
            ->where('status', ContentStatus::Scheduled->value)
            ->where('published_at', '<=', now())
            ->with(['post' => fn ($query) => $query->withoutGlobalScope('site')])
            ->get();

        $published = 0;
        foreach ($due as $translation) {
            /** @var Post|null $post a post deleted meanwhile is skipped */
            $post = $translation->getRelation('post');
            if (! $post instanceof Post) {
                continue;
            }

            $translation->status = ContentStatus::Published;
            $translation->save();
            $this->revisions->handle($post, $translation, Transition::Publish->label());

            activity('workflow')
                ->performedOn($post)
                ->event('scheduled_publish')
                ->withProperties(['locale' => $translation->locale, 'from' => 'scheduled', 'to' => 'published'])
                ->log('post.published');

            ContentTransitioned::dispatch($post, $translation, Transition::Publish, null, null);
            $published++;
        }

        return $published;
    }

    private function permitted(Post $post, Transition $transition, User $actor): bool
    {
        if (! $actor->can('update', $post)) {
            return false;
        }

        $permission = $transition->permission();

        return $permission === null || $actor->can($permission);
    }
}
