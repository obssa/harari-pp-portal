<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Events\ContentTransitioned;
use App\Domain\Content\Models\Comment;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Notifications\WorkflowNotification;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Domain\Webhooks\Jobs\DeliverWebhook;
use App\Domain\Webhooks\Models\Webhook;
use App\Domain\Webhooks\Support\WebhookUrl;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

function draftBy(User $author, string $title = 'Council session'): Post
{
    return app(SavePost::class)->handle(new Post, ['translations' => ['en' => ['title' => $title]]], $author);
}

function move(Post $post, Transition $transition, User $actor, ?string $comment = null, ?DateTimeInterface $at = null): ContentStatus
{
    return app(ContentWorkflow::class)->apply($post, 'en', $transition, $actor, $comment, $at)->status;
}

it('takes a post from draft through review to published', function () {
    $author = staff(SystemRole::Author);
    $editor = staff(SystemRole::Editor);
    $publisher = staff(SystemRole::Publisher);
    $post = draftBy($author);

    expect(move($post, Transition::Submit, $author))->toBe(ContentStatus::InReview)
        ->and(move($post, Transition::MarkReviewed, $editor))->toBe(ContentStatus::InReview)
        ->and($post->translation('en')?->reviewed_by)->toBe($editor->id)
        ->and(move($post, Transition::Publish, $publisher))->toBe(ContentStatus::Published)
        ->and($post->refresh()->translation('en')?->isLive())->toBeTrue()
        ->and(Activity::query()->where('log_name', 'workflow')->count())->toBe(3);
});

it('sends a post back with a required comment', function () {
    $author = staff(SystemRole::Author);
    $editor = staff(SystemRole::Editor);
    $post = draftBy($author);
    move($post, Transition::Submit, $author);

    expect(fn () => move($post, Transition::SendBack, $editor, '  '))->toThrow(ValidationException::class);

    expect(move($post, Transition::SendBack, $editor, 'Please add the date of the session.'))->toBe(ContentStatus::Draft);
    $comment = Comment::query()->firstOrFail();
    expect($comment->kind)->toBe(Comment::KIND_SEND_BACK)
        ->and($comment->user_id)->toBe($editor->id)
        ->and($comment->locale)->toBe('en');
});

it('only offers moves the person may make from the current status', function () {
    $author = staff(SystemRole::Author);
    $post = draftBy($author);
    $workflow = app(ContentWorkflow::class);

    expect($workflow->available($post, 'en', $author))->toBe([Transition::Submit])
        ->and($workflow->available($post, 'en', staff(SystemRole::Publisher)))
        ->toContain(Transition::Submit, Transition::Publish, Transition::Schedule)
        ->and($workflow->available($post, 'am', $author))->toBe([])
        ->and($workflow->available($post, 'en', staff(SystemRole::Author)))->toBe([]);

    expect(fn () => move($post, Transition::Publish, $author))->toThrow(ValidationException::class)
        ->and(fn () => move($post, Transition::Archive, staff(SystemRole::Publisher)))->toThrow(ValidationException::class);
});

it('schedules a post and publishes it when the time comes', function () {
    $publisher = staff(SystemRole::Publisher);
    $post = draftBy($publisher);

    expect(fn () => move($post, Transition::Schedule, $publisher, null, now()->subHour()))->toThrow(ValidationException::class);

    expect(move($post, Transition::Schedule, $publisher, null, now()->addHour()))->toBe(ContentStatus::Scheduled);
    $this->artisan('content:publish-due')->assertSuccessful();
    expect($post->refresh()->translation('en')?->status)->toBe(ContentStatus::Scheduled);

    $this->travel(2)->hours();
    $this->artisan('content:publish-due')->assertSuccessful();
    expect($post->refresh()->translation('en')?->status)->toBe(ContentStatus::Published);
});

it('unpublishes, archives and reopens', function () {
    $publisher = staff(SystemRole::Publisher);
    $post = draftBy($publisher);
    move($post, Transition::Publish, $publisher);

    expect(move($post, Transition::Archive, $publisher))->toBe(ContentStatus::Archived)
        ->and(move($post, Transition::Reopen, $publisher))->toBe(ContentStatus::Draft)
        ->and(move($post, Transition::Publish, $publisher))->toBe(ContentStatus::Published)
        ->and(move($post, Transition::Unpublish, $publisher))->toBe(ContentStatus::Draft);
});

it('notifies reviewers, publishers and the author, never the person who acted', function () {
    Notification::fake();
    $author = staff(SystemRole::Author);
    $editor = staff(SystemRole::Editor);
    $publisher = staff(SystemRole::Publisher);
    $post = draftBy($author);

    move($post, Transition::Submit, $author);
    Notification::assertSentTo($editor, WorkflowNotification::class);
    Notification::assertNotSentTo($author, WorkflowNotification::class);

    move($post, Transition::MarkReviewed, $editor);
    Notification::assertSentTo($publisher, WorkflowNotification::class);

    move($post, Transition::Publish, $publisher);
    Notification::assertSentTo($author, WorkflowNotification::class, fn (WorkflowNotification $n): bool => $n->transition === 'publish');
});

it('writes notifications in the database for the admin bell', function () {
    $author = staff(SystemRole::Author);
    $editor = staff(SystemRole::Editor);
    $post = draftBy($author, 'Water project');

    move($post, Transition::Submit, $author);

    $stored = $editor->notifications()->first();
    expect($stored)->not->toBeNull()
        ->and(json_encode($stored?->data, JSON_UNESCAPED_UNICODE))->toContain('Water project');
});

it('announces every move as an event', function () {
    Event::fake([ContentTransitioned::class]);
    $publisher = staff(SystemRole::Publisher);
    $post = draftBy($publisher);

    move($post, Transition::Publish, $publisher);

    Event::assertDispatched(ContentTransitioned::class, fn (ContentTransitioned $e): bool => $e->webhookEvent() === 'post.published');
});

it('queues signed webhook deliveries for subscribed events only', function () {
    Queue::fake();
    $publisher = staff(SystemRole::Publisher);
    $hook = Webhook::query()->create(['name' => 'Archive', 'url' => 'https://example.org/hook', 'secret' => 's3cret', 'events' => ['post.published']]);
    Webhook::query()->create(['name' => 'Off', 'url' => 'https://example.org/off', 'secret' => 'x', 'events' => ['post.published'], 'is_active' => false]);
    $post = draftBy($publisher);

    move($post, Transition::Schedule, $publisher, null, now()->addDay());
    Queue::assertNothingPushed();

    move($post, Transition::Publish, $publisher);
    Queue::assertPushed(DeliverWebhook::class, 1);
    expect($hook->deliveries()->firstOrFail()->payload['post']['title'] ?? null)->toBe('Council session');
});

it('delivers a webhook with an HMAC signature and logs the result', function () {
    Http::fake(['example.org/*' => Http::response('ok', 200)]);
    $hook = Webhook::query()->create(['name' => 'Archive', 'url' => 'https://93.184.215.14/hook', 'secret' => 's3cret', 'events' => ['post.published']]);
    $delivery = $hook->deliveries()->create(['event' => 'post.published', 'payload' => ['event' => 'post.published']]);
    Http::fake(['93.184.215.14/*' => Http::response('ok', 200)]);

    (new DeliverWebhook($delivery))->handle();

    Http::assertSent(fn ($request): bool => $request->header('X-Portal-Signature')[0] === 'sha256='.hash_hmac('sha256', $request->body(), 's3cret')
        && $request->header('X-Portal-Event')[0] === 'post.published');
    expect($delivery->refresh()->delivered_at)->not->toBeNull()
        ->and($delivery->response_code)->toBe(200)
        ->and($delivery->attempts)->toBe(1);
});

it('refuses webhook addresses that are not public HTTPS', function (string $url, bool $allowed) {
    expect(WebhookUrl::isAllowed($url))->toBe($allowed);
})->with([
    ['https://93.184.215.14/hook', true],
    ['http://93.184.215.14/hook', false],
    ['https://127.0.0.1/hook', false],
    ['https://10.0.0.5/hook', false],
    ['https://192.168.1.10/hook', false],
    ['https://localhost/hook', false],
    ['https://user:pass@93.184.215.14/hook', false],
    ['https://169.254.169.254/latest/meta-data', false],
    ['ftp://93.184.215.14/x', false],
]);
