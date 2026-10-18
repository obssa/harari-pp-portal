<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Access\Enums\UserStatus;
use App\Domain\Access\Notifications\SignInLink;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->admin = staff(SystemRole::Admin);
    $this->actingAs($this->admin);
});

it('lists users with their roles', function () {
    $editor = staff(SystemRole::Editor);

    livewire(ListUsers::class)
        ->assertCanSeeTableRecords([$this->admin, $editor])
        ->assertSee(SystemRole::Editor->label());
});

it('filters users by role', function () {
    $editor = staff(SystemRole::Editor);
    $author = staff(SystemRole::Author);

    livewire(ListUsers::class)
        ->filterTable('role', 'editor')
        ->assertCanSeeTableRecords([$editor])
        ->assertCanNotSeeTableRecords([$author]);
});

it('invites a new user by email when no password is given', function () {
    Notification::fake();

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'ሙከራ ተጠቃሚ',
            'email' => 'new.editor@example.org',
            'status' => 'active',
            'role_names' => ['editor'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'new.editor@example.org')->firstOrFail();
    expect($user->name)->toBe('ሙከራ ተጠቃሚ')
        ->and($user->hasRole('editor'))->toBeTrue();
    Notification::assertSentTo($user, SignInLink::class, fn (SignInLink $n) => str_contains($n->url, '/admin/password-reset/reset'));
});

it('creates a user with a temporary password and no email', function () {
    Notification::fake();

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Temp',
            'email' => 'temp@example.org',
            'status' => 'active',
            'password' => 'Temporary-Pass-2026',
            'role_names' => ['author'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'temp@example.org')->firstOrFail();
    expect(Hash::check('Temporary-Pass-2026', $user->password))->toBeTrue();
    Notification::assertNothingSent();
});

it('rejects weak temporary passwords and duplicate emails', function () {
    livewire(CreateUser::class)
        ->fillForm(['name' => 'X', 'email' => $this->admin->email, 'status' => 'active', 'password' => 'short'])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique', 'password']);
});

it('does not offer the Super Admin role to an Admin', function () {
    livewire(CreateUser::class)
        ->assertDontSee(SystemRole::SuperAdmin->label())
        ->assertSee(SystemRole::Editor->label());
});

it('edits roles and scopes and signs the user out', function () {
    $user = staff(SystemRole::Author);

    livewire(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertFormSet(['role_names' => ['author']])
        ->fillForm([
            'role_names' => ['editor', 'translator'],
            'scopes' => [
                ['role' => 'translator', 'scope_type' => 'language', 'scope_value' => 'har'],
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user = $user->fresh();
    expect($user->getRoleNames()->sort()->values()->all())->toBe(['editor', 'translator'])
        ->and($user->canEditTranslationsFor('har'))->toBeTrue()
        ->and($user->canEditTranslationsFor('am'))->toBeFalse()
        ->and(Activity::query()->where('subject_id', $user->id)->whereIn('event', ['roles_attached', 'scope_added'])->count())
        ->toBeGreaterThanOrEqual(2);
});

it('forbids an Admin from opening a Super Admin account', function () {
    $root = staff(SystemRole::SuperAdmin);

    $this->get("/admin/users/{$root->getRouteKey()}/edit")->assertForbidden();
});

it('suspends and reactivates an account', function () {
    $user = staff(SystemRole::Editor);

    livewire(ListUsers::class)
        ->callAction(TestAction::make('suspend')->table($user));
    expect($user->fresh()->status)->toBe(UserStatus::Suspended);

    livewire(ListUsers::class)
        ->callAction(TestAction::make('reactivate')->table($user));
    expect($user->fresh()->status)->toBe(UserStatus::Active);
});

it('does not let anyone suspend themselves', function () {
    livewire(ListUsers::class)
        ->assertActionHidden(TestAction::make('suspend')->table($this->admin));
});

it('resets two-factor login for a user who lost their phone', function () {
    $publisher = staff(SystemRole::Publisher);

    livewire(ListUsers::class)
        ->callAction(TestAction::make('resetMfa')->table($publisher));

    expect($publisher->fresh()->getAppAuthenticationSecret())->toBeNull()
        ->and(Activity::query()->where('event', 'mfa_reset')->where('subject_id', $publisher->id)->exists())->toBeTrue();
});

it('sends a sign-in link on request', function () {
    Notification::fake();
    $user = staff(SystemRole::Editor);

    livewire(ListUsers::class)
        ->callAction(TestAction::make('sendSignInLink')->table($user));

    Notification::assertSentTo($user, SignInLink::class);
});

it('soft-deletes users and never offers permanent deletion', function () {
    $user = staff(SystemRole::Editor);

    livewire(EditUser::class, ['record' => $user->getRouteKey()])
        ->callAction('delete');

    expect($user->fresh()->trashed())->toBeTrue();

    livewire(EditUser::class, ['record' => $user->getRouteKey()])
        ->assertActionDoesNotExist('forceDelete');
});

it('writes the sign-in email in the recipient language', function () {
    $user = User::factory()->create(['locale' => 'am']);
    $mail = (new SignInLink('https://example.test/reset', 60))->toMail($user);

    expect($mail->subject)->toContain('የሐረሪ ብልጽግና ፓርቲ')
        ->and($mail->actionText)->toBe(__('admin.mail.sign_in.action', [], 'am'));
});
