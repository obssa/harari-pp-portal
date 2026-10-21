<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Api\Models\ApiKey;
use App\Filament\Resources\ApiKeys\Pages\ManageApiKeys;
use Filament\Actions\Testing\TestAction;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

it('lets only Super Admins manage API keys', function () {
    $this->actingAs(staff(SystemRole::Admin))->get('/admin/api-keys')->assertForbidden();
    $this->actingAs(staff(SystemRole::SuperAdmin))->get('/admin/api-keys')->assertOk();
});

it('issues a key shown once, keeps only its hash, and logs the change', function () {
    $this->actingAs(staff(SystemRole::SuperAdmin));

    livewire(ManageApiKeys::class)
        ->callAction('create', data: [
            'name' => 'Sample mobile app (test)',
            'scopes' => ['content', 'events'],
            'rate_per_minute' => 120,
        ])
        ->assertHasNoActionErrors()
        ->assertNotified(__('admin.api_keys.key_title'));

    $key = ApiKey::query()->sole();
    expect($key->scopes)->toBe(['content', 'events'])
        ->and($key->rate_per_minute)->toBe(120)
        ->and($key->prefix)->toStartWith(ApiKey::PREFIX)
        ->and(strlen($key->key_hash))->toBe(64)
        ->and($key->toArray())->not->toHaveKey('key_hash')
        ->and(Activity::query()->where('subject_type', $key->getMorphClass())->where('subject_id', $key->id)->exists())->toBeTrue();
});

it('changes and revokes keys, and a revoked key can no longer be edited', function () {
    $this->actingAs(staff(SystemRole::SuperAdmin));
    livewire(ManageApiKeys::class)->callAction('create', data: ['name' => 'Sample kiosk (test)', 'scopes' => ['events'], 'rate_per_minute' => 60]);
    $key = ApiKey::query()->sole();

    livewire(ManageApiKeys::class)
        ->callAction(TestAction::make('edit')->table($key), data: ['name' => 'Sample kiosk (test)', 'scopes' => ['events', 'documents'], 'rate_per_minute' => 5000])
        ->assertHasNoActionErrors(['scopes'])
        ->assertHasActionErrors(['rate_per_minute']);

    livewire(ManageApiKeys::class)
        ->callAction(TestAction::make('edit')->table($key), data: ['name' => 'Sample kiosk (test)', 'scopes' => ['events', 'documents'], 'rate_per_minute' => 30])
        ->assertHasNoActionErrors();
    expect($key->refresh()->scopes)->toBe(['events', 'documents'])->and($key->rate_per_minute)->toBe(30);

    livewire(ManageApiKeys::class)
        ->callAction(TestAction::make('revoke')->table($key))
        ->assertNotified(__('admin.api_keys.revoked'));
    expect($key->refresh()->revoked_at)->not->toBeNull()->and($key->isUsable())->toBeFalse();

    livewire(ManageApiKeys::class)
        ->assertActionHidden(TestAction::make('edit')->table($key))
        ->assertActionHidden(TestAction::make('revoke')->table($key));
});
