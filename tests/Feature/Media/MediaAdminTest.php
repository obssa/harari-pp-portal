<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Media\Actions\DeleteMedia;
use App\Domain\Media\Actions\SyncMediaUsage;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Exceptions\MediaInUse;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaFolder;
use App\Filament\Resources\Media\Pages\EditMedia;
use App\Filament\Resources\Media\Pages\ListMedia;
use App\Filament\Resources\Media\Widgets\MediaStorageOverview;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\TestFiles;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Storage::fake('media');
    Storage::fake('public');
    config(['portal.media.avif' => false]);
});

function libraryFile(string $name = 'flag.jpg', ?User $uploader = null): Media
{
    return app(UploadMedia::class)->handle(TestFiles::image(300, 200), $name, $uploader);
}

it('opens the media library for roles that may see media, and refuses the others', function (SystemRole $role, int $status) {
    $this->actingAs(staff($role))->get('/admin/media')->assertStatus($status);
})->with([
    'media manager' => [SystemRole::MediaManager, 200],
    'editor' => [SystemRole::Editor, 200],
    'author' => [SystemRole::Author, 200],
    'communications officer' => [SystemRole::CommunicationsOfficer, 200],
    'translator' => [SystemRole::Translator, 403],
    'membership officer' => [SystemRole::MembershipOfficer, 403],
    'analyst' => [SystemRole::Analyst, 403],
]);

it('follows the media permissions of each role', function () {
    $author = staff(SystemRole::Author);
    $editor = staff(SystemRole::Editor);
    $manager = staff(SystemRole::MediaManager);
    $own = libraryFile('own.jpg', $author);
    $other = libraryFile('other.jpg', $manager);

    expect($author->can('create', Media::class))->toBeTrue()
        ->and($author->can('update', $own))->toBeTrue()
        ->and($author->can('update', $other))->toBeFalse()
        ->and($author->can('delete', $own))->toBeFalse()
        ->and($editor->can('update', $other))->toBeTrue()
        ->and($editor->can('delete', $other))->toBeFalse()
        ->and($manager->can('delete', $other))->toBeTrue()
        ->and($manager->can('restore', $other))->toBeFalse()
        ->and($manager->can('forceDelete', $other))->toBeFalse();
});

it('lists the library with filters for missing alt text and unused files', function () {
    $this->actingAs(staff(SystemRole::MediaManager));
    $described = libraryFile('described.jpg');
    $described->update(['alt' => ['en' => 'A', 'am' => 'ሀ', 'om' => 'A']]);
    $bare = libraryFile('bare.jpg');
    app(SyncMediaUsage::class)->handle(staff(SystemRole::Member), 'avatar', [$described->id]);

    livewire(ListMedia::class)
        ->assertCanSeeTableRecords([$described, $bare])
        ->filterTable('missing_alt')
        ->assertCanSeeTableRecords([$bare])
        ->assertCanNotSeeTableRecords([$described])
        ->resetTableFilters()
        ->filterTable('unused')
        ->assertCanSeeTableRecords([$bare])
        ->assertCanNotSeeTableRecords([$described]);
});

it('uploads several files at once and reports the refused ones', function () {
    $user = staff(SystemRole::Author);
    $this->actingAs($user);
    $folder = MediaFolder::query()->create(['name' => 'Rallies']);

    livewire(ListMedia::class)
        ->callAction('upload', data: [
            'files' => [
                UploadedFile::fake()->createWithContent('rally.jpg', (string) file_get_contents(TestFiles::image(200, 100))),
                UploadedFile::fake()->createWithContent('notes.txt', "Agenda\n"),
            ],
            'folder_id' => $folder->id,
        ])
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect(Media::query()->where('uploaded_by', $user->id)->where('folder_id', $folder->id)->count())->toBe(2);
});

it('tells the uploader which files were refused and why', function () {
    $this->actingAs(staff(SystemRole::Author));

    livewire(ListMedia::class)
        ->callAction('upload', data: [
            'files' => [UploadedFile::fake()->createWithContent('shell.txt', '<?php echo 1;')],
        ])
        ->assertNotified(__('admin.media.uploaded', ['count' => 0]));

    expect(Media::query()->count())->toBe(0);
});

it('shows the upload button to roles that may upload', function (SystemRole $role) {
    $this->actingAs(staff($role));

    livewire(ListMedia::class)->assertActionVisible('upload');
})->with([SystemRole::Author, SystemRole::CommunicationsOfficer, SystemRole::Admin]);

it('saves alt text per language, credit, tags and focal point, and audits the change', function () {
    $this->actingAs(staff(SystemRole::MediaManager));
    $media = libraryFile();

    livewire(EditMedia::class, ['record' => $media->getRouteKey()])
        ->fillForm([
            'name' => 'National flag',
            'alt' => ['en' => ' The flag ', 'am' => 'ሰንደቅ ዓላማ', 'om' => '', 'har' => ''],
            'credit' => 'Communication office (sample)',
            'tags' => ['flag', ' flag ', 'symbols'],
            'focal_x' => 20,
            'focal_y' => 70,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $media->refresh();
    expect($media->name)->toBe('National flag')
        ->and($media->alt)->toEqual(['en' => 'The flag', 'am' => 'ሰንደቅ ዓላማ'])
        ->and($media->altFor('har'))->toBe('ሰንደቅ ዓላማ')
        ->and($media->altFor('om'))->toBe('The flag')
        ->and($media->tags)->toBe(['flag', 'symbols'])
        ->and([$media->focal_x, $media->focal_y])->toBe([20, 70])
        ->and(Activity::query()->where('log_name', 'media')->where('subject_id', $media->id)->where('event', 'updated')->exists())->toBeTrue();
});

it('validates the focal point range', function () {
    $this->actingAs(staff(SystemRole::MediaManager));

    livewire(EditMedia::class, ['record' => libraryFile()->getRouteKey()])
        ->fillForm(['focal_x' => 120, 'focal_y' => -1])
        ->call('save')
        ->assertHasFormErrors(['focal_x', 'focal_y']);
});

it('lets authors edit their own uploads only', function () {
    $author = staff(SystemRole::Author);
    $own = libraryFile('own.jpg', $author);
    $other = libraryFile('other.jpg');

    $this->actingAs($author)->get("/admin/media/{$own->id}/edit")->assertOk();
    $this->actingAs($author)->get("/admin/media/{$other->id}/edit")->assertForbidden();
});

it('records where files are used and keeps used files from being deleted', function () {
    $manager = staff(SystemRole::MediaManager);
    $this->actingAs($manager);
    $media = libraryFile();
    $page = staff(SystemRole::Member);

    app(SyncMediaUsage::class)->handle($page, 'featured_image', [$media->id]);
    expect($media->isUsed())->toBeTrue()
        ->and($manager->can('delete', $media))->toBeFalse()
        ->and(fn () => app(DeleteMedia::class)->handle($media))->toThrow(MediaInUse::class);

    app(SyncMediaUsage::class)->handle($page, 'featured_image', []);
    expect($media->isUsed())->toBeFalse();

    app(DeleteMedia::class)->handle($media);
    expect(Media::query()->find($media->id))->toBeNull()
        ->and(Storage::disk('media')->allFiles())->toBe([])
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('syncs usage idempotently per place and forgets it for a removed model', function () {
    $a = libraryFile('a.jpg');
    $b = libraryFile('b.jpg');
    $owner = staff(SystemRole::Member);
    $sync = app(SyncMediaUsage::class);

    $sync->handle($owner, 'gallery', [$a->id, $b->id, $a->id, null]);
    $sync->handle($owner, 'gallery', [$b->id]);
    $sync->handle($owner, 'featured_image', [$a->id]);

    expect($a->usages()->pluck('context')->all())->toBe(['featured_image'])
        ->and($b->usages()->pluck('context')->all())->toBe(['gallery']);

    $sync->forget($owner);
    expect($a->isUsed() || $b->isUsed())->toBeFalse();
});

it('deletes in bulk but keeps files that are in use', function () {
    $this->actingAs(staff(SystemRole::MediaManager));
    $used = libraryFile('used.jpg');
    $free = libraryFile('free.jpg');
    app(SyncMediaUsage::class)->handle(staff(SystemRole::Member), 'avatar', [$used->id]);

    livewire(ListMedia::class)
        ->callTableBulkAction('delete', [$used, $free])
        ->assertNotified();

    expect(Media::query()->pluck('id')->all())->toBe([$used->id]);
});

it('moves and tags files in bulk', function () {
    $this->actingAs(staff(SystemRole::MediaManager));
    $folder = MediaFolder::query()->create(['name' => 'Archive']);
    $files = [libraryFile('a.jpg'), libraryFile('b.jpg')];

    livewire(ListMedia::class)
        ->callTableBulkAction('move', $files, data: ['folder_id' => $folder->id])
        ->callTableBulkAction('tag', $files, data: ['tags' => ['2026', 'rally']]);

    foreach ($files as $file) {
        $file->refresh();
        expect($file->folder_id)->toBe($folder->id)->and($file->tags)->toBe(['2026', 'rally']);
    }
});

it('shows storage against the quota and the work left', function () {
    $this->actingAs(staff(SystemRole::MediaManager));
    libraryFile('a.jpg');
    libraryFile('b.jpg');

    livewire(MediaStorageOverview::class)
        ->assertSee(__('admin.media.stats.files'))
        ->assertSee(__('admin.media.stats.missing_alt'))
        ->assertSee('2');
});
