<?php

use App\Domain\Access\Actions\InvalidateUserSessions;
use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

it('records changes to an account with before and after values but never secrets', function () {
    $user = User::factory()->create(['name' => 'Before']);
    $user->update(['name' => 'After', 'password' => 'Another-Secret-2026']);
    $user->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    $entries = Activity::query()->where('subject_type', $user->getMorphClass())->where('subject_id', $user->id)
        ->where('event', 'updated')->get();

    expect($entries)->toHaveCount(1);
    $props = $entries->first()->properties;
    expect($props['old']['name'])->toBe('Before')
        ->and($props['attributes']['name'])->toBe('After')
        ->and($props['attributes'])->not->toHaveKeys(['password', 'app_authentication_secret', 'remember_token']);
});

it('records role grants, scope changes and signs the person out everywhere', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    DB::table('sessions')->insert([
        ['id' => 'a', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'b', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()],
    ]);
    $token = $user->remember_token;

    $user->assignRole('editor');

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->fresh()->remember_token)->not->toBe($token);

    $user->assignScopedRole(SystemRole::Editor, ScopeType::League, 'youth');
    $user->roleScopes()->first()->delete();
    $user->removeRole('editor');

    $events = Activity::query()->where('subject_id', $user->id)->where('log_name', 'users')->pluck('event')->all();
    expect($events)->toContain('roles_attached', 'scope_added', 'scope_removed', 'roles_detached');

    $granted = Activity::query()->where('subject_id', $user->id)->where('event', 'roles_attached')->firstOrFail();
    expect($granted->getExtraProperty('roles'))->toBe(['editor']);
});

it('leaves other people signed in', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    $other = User::factory()->create();
    DB::table('sessions')->insert(['id' => 'keep', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()]);

    app(InvalidateUserSessions::class)($user);

    expect(DB::table('sessions')->where('id', 'keep')->exists())->toBeTrue();
});

it('records sign-ins with time and address, and failed attempts by email only', function () {
    $user = staff(SystemRole::Editor, attributes: ['email' => 'editor@example.org', 'password' => 'Correct-Horse-2026']);

    livewire(Login::class)
        ->fillForm(['email' => 'editor@example.org', 'password' => 'wrong-password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $failed = Activity::query()->where('event', 'login_failed')->latest('id')->firstOrFail();
    expect($failed->getExtraProperty('email'))->toBe('editor@example.org')
        ->and(json_encode($failed->properties))->not->toContain('wrong-password');

    livewire(Login::class)
        ->fillForm(['email' => 'editor@example.org', 'password' => 'Correct-Horse-2026'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    $user = $user->fresh();
    expect($user->last_login_at)->not->toBeNull()
        ->and(Activity::query()->where('event', 'login')->where('causer_id', $user->id)->exists())->toBeTrue();
});

it('records sign-outs with the request IP', function () {
    $user = staff(SystemRole::Editor);

    $this->actingAs($user)->post('/admin/logout')->assertRedirect();

    $entry = Activity::query()->where('event', 'logout')->latest('id')->firstOrFail();
    expect($entry->causer?->is($user))->toBeTrue()
        ->and($entry->getExtraProperty('ip'))->toBe('127.0.0.1');
});
