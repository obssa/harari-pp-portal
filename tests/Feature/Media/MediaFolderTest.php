<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Models\MediaFolder;
use App\Domain\Sites\Models\Site;
use App\Filament\Resources\MediaFolders\Pages\ManageMediaFolders;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\Storage;
use Tests\Support\TestFiles;

use function Pest\Livewire\livewire;

beforeEach(fn () => $this->actingAs(staff(SystemRole::MediaManager)));

it('creates nested folders for the current site', function () {
    livewire(ManageMediaFolders::class)
        ->callAction('create', data: ['name' => 'Events'])
        ->assertHasNoActionErrors();
    $events = MediaFolder::query()->where('name', 'Events')->firstOrFail();

    livewire(ManageMediaFolders::class)
        ->callAction('create', data: ['name' => '2026', 'parent_id' => $events->id])
        ->assertHasNoActionErrors();
    $year = MediaFolder::query()->where('name', '2026')->firstOrFail();

    expect($events->site_id)->toBe(Site::current()->id)
        ->and($year->parent_id)->toBe($events->id)
        ->and($year->path())->toBe('Events / 2026')
        ->and(MediaFolder::options())->toBe([$events->id => 'Events', $year->id => 'Events / 2026']);
});

it('refuses two folders with the same name in one place', function () {
    MediaFolder::query()->create(['name' => 'Events']);

    livewire(ManageMediaFolders::class)
        ->callAction('create', data: ['name' => 'Events'])
        ->assertHasActionErrors(['name']);
});

it('never offers a folder or its subfolders as its own parent', function () {
    $a = MediaFolder::query()->create(['name' => 'A']);
    $b = MediaFolder::query()->create(['name' => 'B', 'parent_id' => $a->id]);
    $c = MediaFolder::query()->create(['name' => 'C', 'parent_id' => $b->id]);
    $other = MediaFolder::query()->create(['name' => 'Other']);

    expect($a->selfAndDescendantIds())->toEqualCanonicalizing([$a->id, $b->id, $c->id])
        ->and(array_keys(MediaFolder::options(except: $a->selfAndDescendantIds())))->toBe([$other->id]);
});

it('deletes empty folders only', function () {
    Storage::fake('media');
    Storage::fake('public');
    config(['portal.media.avif' => false]);
    $empty = MediaFolder::query()->create(['name' => 'Empty']);
    $full = MediaFolder::query()->create(['name' => 'Full']);
    app(UploadMedia::class)->handle(TestFiles::image(20, 20), 'x.jpg', null, $full);

    livewire(ManageMediaFolders::class)
        ->assertTableActionHidden(DeleteAction::class, $full)
        ->callTableAction(DeleteAction::class, $empty);

    expect(MediaFolder::query()->pluck('name')->all())->toBe(['Full']);
});

it('renames folders', function () {
    $folder = MediaFolder::query()->create(['name' => 'Evnts']);

    livewire(ManageMediaFolders::class)
        ->callTableAction(EditAction::class, $folder, data: ['name' => 'Events'])
        ->assertHasNoTableActionErrors();

    expect($folder->fresh()?->name)->toBe('Events');
});

it('keeps folders away from roles without media access', function () {
    $this->actingAs(staff(SystemRole::Translator))->get('/admin/media-folders')->assertForbidden();
});
