<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\Post;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Posts\RelationManagers\CommentsRelationManager;
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
                'am' => ['title' => 'የሰላም መግለጫ', 'excerpt' => 'አጭር ማጠቃለያ'],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = Post::query()->with('translations')->firstOrFail();
    expect($post->type->value)->toBe('statement')
        ->and($post->translation('am', fallback: false)?->status)->toBe(ContentStatus::Draft);
});

it('shows a form error when no language has a title', function () {
    $this->actingAs(staff(SystemRole::Editor));

    livewire(CreatePost::class)
        ->fillForm(['translations' => ['en' => ['title' => '']]])
        ->call('create')
        ->assertHasFormErrors(['translations.en.title']);
});

it('shows each person only the workflow buttons they may use', function () {
    $author = staff(SystemRole::Author);
    $post = app(SavePost::class)->handle(new Post, ['translations' => ['en' => ['title' => 'Workflow post']]], $author);

    $this->actingAs($author)->get(PostResource::getUrl('edit', ['record' => $post]))
        ->assertOk()
        ->assertSee(__('content.transition.submit'))
        ->assertDontSee(__('content.transition.publish'));

    $this->actingAs(staff(SystemRole::Publisher))->get(PostResource::getUrl('edit', ['record' => $post]))
        ->assertOk()
        ->assertSee(__('content.transition.publish'))
        ->assertSee(__('content.transition.schedule'));
});

it('edits a post, keeps its status, and fills the form from all languages', function () {
    $post = adminPost('Draft title');
    $this->actingAs(staff(SystemRole::Publisher));

    livewire(EditPost::class, ['record' => $post->getRouteKey()])
        ->assertFormSet(['translations.en.title' => 'Draft title'])
        ->fillForm([
            'translations.en.title' => 'Final title',
            'translations.om.title' => 'Mata-duree xumuraa',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $post->refresh();
    expect($post->title('en'))->toBe('Final title')
        ->and($post->translation('en')?->status)->toBe(ContentStatus::Draft)
        ->and($post->translation('om', fallback: false)?->status)->toBe(ContentStatus::Draft)
        ->and($post->translatedLocales())->toBe(['en', 'om']);
});

it('adds internal comments to a post', function () {
    $post = adminPost();
    $editor = staff(SystemRole::Editor);
    $this->actingAs($editor);

    livewire(CommentsRelationManager::class, ['ownerRecord' => $post, 'pageClass' => EditPost::class])
        ->callTableAction('create', data: ['body' => 'Check the figures with the finance office.', 'locale' => 'en'])
        ->assertHasNoTableActionErrors();

    $comment = $post->comments()->firstOrFail();
    expect($comment->user_id)->toBe($editor->id)->and($comment->kind)->toBe('comment');
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
