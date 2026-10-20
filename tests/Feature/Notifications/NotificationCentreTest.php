<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Import\Notifications\StoriesImportedNotice;
use App\Domain\Notifications\Support\NoticePreferences;
use App\Filament\Pages\NotificationCentre;
use App\Filament\Resources\Posts\PostResource;
use App\Models\User;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Notifications\DatabaseNotification;

use function Pest\Livewire\livewire;

/** A stored notice for the person, as the queue would write it. */
function centreNotice(User $user, int $count = 1): DatabaseNotification
{
    $before = $user->notifications()->pluck('id')->all();
    $user->notify(new StoriesImportedNotice($count));

    /** @var DatabaseNotification */
    return $user->notifications()->whereNotIn('id', $before)->firstOrFail();
}

it('lists only the person\'s own notices and filters what is unread', function () {
    $editor = staff(SystemRole::Editor);
    $read = centreNotice($editor, 1);
    $read->markAsRead();
    $unread = centreNotice($editor, 2);
    $someoneElse = centreNotice(staff(SystemRole::Editor), 3);

    $this->actingAs($editor);

    livewire(NotificationCentre::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$read, $unread])
        ->assertCanNotSeeTableRecords([$someoneElse])
        ->filterTable('read', true)
        ->assertCanSeeTableRecords([$unread])
        ->assertCanNotSeeTableRecords([$read])
        ->filterTable('read', false)
        ->assertCanSeeTableRecords([$read])
        ->assertCanNotSeeTableRecords([$unread]);

    expect(NotificationCentre::getNavigationBadge())->toBe('1');
});

it('marks notices read and unread, all at once, and deletes them', function () {
    $editor = staff(SystemRole::Editor);
    $first = centreNotice($editor, 1);
    $second = centreNotice($editor, 2);
    $this->actingAs($editor);

    livewire(NotificationCentre::class)
        ->callAction(TestAction::make('markRead')->table($first))
        ->assertHasNoErrors();
    expect($first->refresh()->read_at)->not->toBeNull();

    livewire(NotificationCentre::class)->callAction(TestAction::make('markUnread')->table($first));
    expect($first->refresh()->read_at)->toBeNull();

    livewire(NotificationCentre::class)->callAction('markAllRead');
    expect($editor->unreadNotifications()->count())->toBe(0)
        ->and(NotificationCentre::getNavigationBadge())->toBeNull();

    livewire(NotificationCentre::class)->callAction(TestAction::make('delete')->table($second));
    expect(DatabaseNotification::query()->find($second->id))->toBeNull()
        ->and(DatabaseNotification::query()->find($first->id))->not->toBeNull();
});

it('opens the page a notice links to and marks it read', function () {
    $editor = staff(SystemRole::Editor);
    $notice = centreNotice($editor);
    $this->actingAs($editor);

    livewire(NotificationCentre::class)
        ->callAction(TestAction::make('open')->table($notice))
        ->assertRedirect(PostResource::getUrl('index'));

    expect($notice->refresh()->read_at)->not->toBeNull();
});

it('does not act on another person\'s notice', function () {
    $editor = staff(SystemRole::Editor);
    $other = centreNotice(staff(SystemRole::Editor));
    $this->actingAs($editor);

    expect(fn () => livewire(NotificationCentre::class)->callAction(TestAction::make('delete')->table($other)))
        ->toThrow(ActionNotResolvableException::class);

    expect(DatabaseNotification::query()->find($other->id))->not->toBeNull();
});

it('saves how the person wants each kind of notice', function () {
    $agent = staff(SystemRole::SupportAgent);
    $this->actingAs($agent);

    livewire(NotificationCentre::class)
        ->mountAction('preferences')
        ->assertActionDataSet(['tickets' => [NoticePreferences::IN_APP, NoticePreferences::EMAIL]])
        ->set('mountedActions.0.data.tickets', [NoticePreferences::EMAIL])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified(__('notifications.preferences.saved'));

    expect($agent->refresh()->notification_preferences)->toBe(['tickets' => [NoticePreferences::EMAIL]]);
});

it('is open to every signed-in member of staff', function () {
    $this->actingAs(staff(SystemRole::Analyst))
        ->get(NotificationCentre::getUrl())
        ->assertOk();

    auth()->logout();
    $this->get(NotificationCentre::getUrl())->assertRedirect();
});
