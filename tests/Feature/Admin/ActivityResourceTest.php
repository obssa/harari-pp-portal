<?php

use App\Domain\Access\Enums\SystemRole;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Activities\Pages\ViewActivity;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

beforeEach(fn () => $this->actingAs(staff(SystemRole::Admin)));

it('lists audit entries newest first and filters by area', function () {
    $editor = staff(SystemRole::Editor);
    $editor->update(['name' => 'Renamed Editor']);
    $update = Activity::query()->where('event', 'updated')->latest('id')->firstOrFail();

    livewire(ListActivities::class)
        ->assertCanSeeTableRecords([$update])
        ->filterTable('log_name', 'users')
        ->assertCanSeeTableRecords([$update]);
});

it('shows the before and after values of a change', function () {
    $editor = staff(SystemRole::Editor, attributes: ['name' => 'Old Name']);
    $editor->update(['name' => 'New Name']);
    $entry = Activity::query()->where('event', 'updated')->latest('id')->firstOrFail();

    livewire(ViewActivity::class, ['record' => $entry->getRouteKey()])
        ->assertSee('Old Name')
        ->assertSee('New Name');
});
