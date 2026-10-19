<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Post;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\RelationManagers\RevisionsRelationManager;

use function Pest\Livewire\livewire;

function adminPost(string $title = 'Sample post', array $more = []): Post
{
    return app(SavePost::class)->handle(new Post, [
        'translations' => ['en' => ['title' => $title, ...$more]],
    ], staff(SystemRole::Publisher));
}

it('opens the posts screens for roles that work on posts', function (SystemRole $role, int $status) {
    $this->actingAs(staff($role))->get('/admin/posts')->assertStatus($status);
})->with([
    'author' => [SystemRole::Author, 200],
    'editor' => [SystemRole::Editor, 200],
    'translator' => [SystemRole::Translator, 200],
    'publisher' => [SystemRole::Publisher, 200],
    'membership officer' => [SystemRole::MembershipOfficer, 403],
]);

it('creates a post from the admin form and opens it for editing', function () {
    $this->actingAs(staff(SystemRole::Editor));

    livewire(CreatePost::class)
        ->fillForm([
            'type' => 'statement',
            'translations' => [
                'am' => ['title' => 'የሰላም መግለጫ', 'excerpt' => 'አጭር ማጠቃለያ', 'status' => 'in_review'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = Post::query()->with('translations')->firstOrFail();
    expect($post->type->value)->toBe('statement')
        ->and($post->translation('am', fallback: false)?->status)->toBe(ContentStatus::InReview);
});

it('shows a form error when no language has a title', function () {
    $this->actingAs(staff(SystemRole::Editor));

    livewire(CreatePost::class)
        ->fillForm(['translations' => ['en' => ['title' => '']]])
        ->call('create')
        ->assertHasFormErrors(['translations.en.title']);
});

it('offers publishing only to publishers', function () {
    $post = adminPost();

    $this->actingAs(staff(SystemRole::Editor));
    livewire(EditPost::class, ['record' => $post->getRouteKey()])
        ->fillForm(['translations.en.status' => 'published'])
        ->call('save')
        ->assertHasFormErrors(['translations.en.status']);
    expect($post->refresh()->translation('en')?->status)->toBe(ContentStatus::Draft);

    $this->actingAs(staff(SystemRole::Publisher));
    livewire(EditPost::class, ['record' => $post->getRouteKey()])
        ->fillForm(['translations.en.status' => 'published'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($post->refresh()->translation('en')?->status)->toBe(ContentStatus::Published)
        ->and(array_keys(ContentStatus::options(false)))->toBe(['draft', 'in_review', 'translating']);
});

it('edits a post, fills the form from all languages, and publishes', function () {
    $post = adminPost('Draft title');
    $this->actingAs(staff(SystemRole::Publisher));

    livewire(EditPost::class, ['record' => $post->getRouteKey()])
        ->assertFormSet(['translations.en.title' => 'Draft title', 'translations.en.status' => 'draft'])
        ->fillForm([
            'translations.en.title' => 'Final title',
            'translations.en.status' => 'published',
            'translations.om.title' => 'Mata-duree xumuraa',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $post->refresh();
    expect($post->title('en'))->toBe('Final title')
        ->and($post->translation('en')?->status)->toBe(ContentStatus::Published)
        ->and($post->translatedLocales())->toBe(['en', 'om']);
});

it('lists posts with their languages and filters by a missing language', function () {
    $this->actingAs(staff(SystemRole::Editor));
    $english = adminPost('Only English');
    $both = app(SavePost::class)->handle(new Post, ['translations' => [
        'en' => ['title' => 'Both'], 'am' => ['title' => 'ሁለቱም'],
    ]], staff(SystemRole::Editor));

    livewire(ListPosts::class)
        ->assertCanSeeTableRecords([$english, $both])
        ->filterTable('missing', 'am')
        ->assertCanSeeTableRecords([$english])
        ->assertCanNotSeeTableRecords([$both]);
});

it('duplicates a post from the edit page', function () {
    $post = adminPost('Original');
    $this->actingAs(staff(SystemRole::Editor));

    livewire(EditPost::class, ['record' => $post->getRouteKey()])->callAction('duplicate');

    expect(Post::query()->count())->toBe(2);
});

it('compares and restores versions from the edit page', function () {
    $publisher = staff(SystemRole::Publisher);
    $this->actingAs($publisher);
    $post = adminPost('Version one');
    app(SavePost::class)->handle($post, ['translations' => ['en' => ['title' => 'Version two']]], $publisher);
    $first = $post->revisions()->oldest('id')->firstOrFail();

    livewire(RevisionsRelationManager::class, ['ownerRecord' => $post, 'pageClass' => EditPost::class])
        ->assertCanSeeTableRecords($post->revisions()->get())
        ->mountTableAction('compare', $first)
        ->assertSee('Version one')
        ->assertSee('Version two');

    livewire(RevisionsRelationManager::class, ['ownerRecord' => $post, 'pageClass' => EditPost::class])
        ->callTableAction('restore', $first);

    expect($post->refresh()->title('en'))->toBe('Version one');
});
