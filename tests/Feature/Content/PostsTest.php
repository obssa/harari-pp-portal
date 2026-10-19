<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\DuplicatePost;
use App\Domain\Content\Actions\RestoreRevision;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Enums\PostType;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\Revision;
use App\Domain\Content\Models\Tag;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Content\Support\TextDiff;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestFiles;

/**
 * @param  list<array<string, mixed>>  $blocks
 * @return array<string, mixed>
 */
function body(string ...$paragraphs): array
{
    return [
        'type' => 'doc',
        'content' => array_map(fn (string $text): array => [
            'type' => 'paragraph',
            'content' => [['type' => 'text', 'text' => $text]],
        ], $paragraphs),
    ];
}

/**
 * @param  array<string, mixed>  $data
 */
function savePost(User $actor, array $data, ?Post $post = null): Post
{
    return app(SavePost::class)->handle($post ?? new Post, $data, $actor);
}

function photo(array $alt = []): Media
{
    Storage::fake('media');
    Storage::fake('public');
    config(['portal.media.avif' => false]);
    $media = app(UploadMedia::class)->handle(TestFiles::image(400, 300), 'rally.jpg');
    $media->update(['alt' => $alt === [] ? null : $alt]);

    return $media->refresh();
}

it('saves a post in several languages with categories, tags, counts and versions', function () {
    $editor = staff(SystemRole::Editor);
    $this->actingAs($editor);
    $category = new Category;
    $category->save();
    $tag = new Tag;
    $tag->save();

    $post = savePost($editor, [
        'type' => PostType::PressRelease->value,
        'source_locale' => 'am',
        'categories' => [$category->id],
        'tags' => [$tag->id],
        'translations' => [
            'am' => ['title' => 'የክልሉ ምክር ቤት ጉባኤ', 'body' => body('ምክር ቤቱ ዛሬ ተሰብስቧል።', 'ሁለተኛ አንቀጽ')],
            'en' => ['title' => 'Regional council meets (sample)', 'excerpt' => ' Short summary. ', 'body' => body('The council met today.')],
            'om' => ['title' => ''],
        ],
    ]);

    $am = $post->translation('am', fallback: false);
    expect($post->type)->toBe(PostType::PressRelease)
        ->and($post->created_by)->toBe($editor->id)
        ->and($post->categories->modelKeys())->toBe([$category->id])
        ->and($post->tags->modelKeys())->toBe([$tag->id])
        ->and($post->translatedLocales())->toBe(['en', 'am'])
        ->and($am?->slug)->toBe('yekililu-mikir-bet-gubae')
        ->and($am?->status)->toBe(ContentStatus::Draft)
        ->and($am?->word_count)->toBe(6)
        ->and($am?->reading_time)->toBe(1)
        ->and($post->translation('en')?->excerpt)->toBe('Short summary.')
        ->and($post->revisions()->count())->toBe(2);
});

it('refuses a post without a title in any language', function () {
    expect(fn () => savePost(staff(SystemRole::Editor), ['translations' => ['en' => ['title' => ' ']]]))
        ->toThrow(ValidationException::class)
        ->and(Post::query()->count())->toBe(0);
});

it('lets only publishers publish, and stamps the publication time', function () {
    $author = staff(SystemRole::Author);

    expect(fn () => savePost($author, ['translations' => ['en' => ['title' => 'Draft news', 'status' => 'published']]]))
        ->toThrow(ValidationException::class);

    $post = savePost(staff(SystemRole::Publisher), ['translations' => ['en' => ['title' => 'Live news', 'status' => 'published']]]);
    $translation = $post->translation('en');

    expect($translation?->status)->toBe(ContentStatus::Published)
        ->and($translation?->published_at)->not->toBeNull()
        ->and($translation?->isLive())->toBeTrue()
        ->and(Post::query()->publishedIn('en')->count())->toBe(1)
        ->and(Post::query()->publishedIn('am')->count())->toBe(0);
});

it('keeps a published language as it is when a non-publisher edits the text', function () {
    $post = savePost(staff(SystemRole::Publisher), ['translations' => ['en' => ['title' => 'Live news', 'status' => 'published']]]);

    $post = savePost(staff(SystemRole::Editor), ['translations' => ['en' => ['title' => 'Live news, corrected', 'status' => 'published']]], $post);

    expect($post->translation('en')?->title)->toBe('Live news, corrected')
        ->and($post->translation('en')?->status)->toBe(ContentStatus::Published);
});

it('lets translators change the text but not the post itself', function () {
    $editor = staff(SystemRole::Editor);
    $category = new Category;
    $category->save();
    $post = savePost($editor, ['type' => 'news', 'translations' => ['en' => ['title' => 'Budget approved']]]);

    $post = savePost(staff(SystemRole::Translator), [
        'type' => 'speech',
        'categories' => [$category->id],
        'translations' => ['en' => ['title' => 'Budget approved'], 'om' => ['title' => 'Baajatni raggaasifame']],
    ], $post);

    expect($post->type)->toBe(PostType::News)
        ->and($post->categories)->toBeEmpty()
        ->and($post->translatedLocales())->toBe(['en', 'om']);
});

it('needs alt text on the featured image for every live language', function () {
    $publisher = staff(SystemRole::Publisher);
    $media = photo(['en' => 'Council members']);

    expect(fn () => savePost($publisher, [
        'featured_image_id' => $media->id,
        'translations' => [
            'en' => ['title' => 'Council', 'status' => 'published'],
            'am' => ['title' => 'ምክር ቤት', 'status' => 'published'],
        ],
    ]))->toThrow(ValidationException::class)
        ->and(Post::query()->count())->toBe(0);

    $post = savePost($publisher, [
        'featured_image_id' => $media->id,
        'translations' => [
            'en' => ['title' => 'Council', 'status' => 'published'],
            'am' => ['title' => 'ምክር ቤት', 'status' => 'draft'],
        ],
    ]);

    expect($post->featured_image_id)->toBe($media->id)
        ->and($media->usages()->pluck('context')->all())->toBe(['featured_image']);
});

it('records library files used in the body and frees them when removed', function () {
    $editor = staff(SystemRole::Editor);
    $media = photo(['en' => 'x']);
    $withBlock = [
        'type' => 'doc',
        'content' => [[
            'type' => 'customBlock',
            'attrs' => ['id' => 'image', 'config' => ['media_id' => $media->id, 'caption' => 'Crowd']],
        ]],
    ];

    $post = savePost($editor, ['translations' => ['en' => ['title' => 'With photo', 'body' => $withBlock]]]);
    expect($media->usages()->pluck('context')->all())->toBe(['body'])
        ->and(BodyRenderer::mediaIds($withBlock))->toBe([$media->id]);

    savePost($editor, ['translations' => ['en' => ['title' => 'With photo', 'body' => body('No photo now')]]], $post);
    expect($media->isUsed())->toBeFalse();
});

it('renders the body blocks to safe HTML', function () {
    $media = photo(['en' => 'Crowd at the rally']);
    $html = BodyRenderer::toHtml([
        'type' => 'doc',
        'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Intro <script>alert(1)</script>']]],
            ['type' => 'customBlock', 'attrs' => ['id' => 'image', 'config' => ['media_id' => $media->id, 'caption' => 'At the rally']]],
            ['type' => 'customBlock', 'attrs' => ['id' => 'video', 'config' => ['url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'Speech']]],
            ['type' => 'customBlock', 'attrs' => ['id' => 'callout', 'config' => ['tone' => 'warning', 'text' => 'Note this']]],
            ['type' => 'customBlock', 'attrs' => ['id' => 'video', 'config' => ['url' => 'https://evil.example/x', 'title' => 'Bad']]],
        ],
    ]);

    expect($html)->toContain('Intro &lt;script&gt;', '<figure', 'At the rally', 'alt="Crowd at the rally"')
        ->toContain('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', 'loading="lazy"', 'role="note"', 'Note this')
        ->not->toContain('evil.example')
        ->not->toContain('<script>');
});

it('counts Ethiopic and Latin words', function () {
    expect(BodyRenderer::wordCount(body('የሐረሪ ክልል ብልጽግና ፓርቲ', 'Two words')))->toBe(6)
        ->and(BodyRenderer::wordCount(null))->toBe(0)
        ->and(BodyRenderer::isEmpty(['type' => 'doc', 'content' => []]))->toBeTrue();
});

it('keeps versions only when the text changed, and restores one without touching status or address', function () {
    $publisher = staff(SystemRole::Publisher);
    $this->actingAs($publisher);
    $post = savePost($publisher, ['translations' => ['en' => ['title' => 'First title', 'body' => body('Old text'), 'status' => 'published']]]);
    $post = savePost($publisher, ['translations' => ['en' => ['title' => 'First title', 'body' => body('Old text'), 'status' => 'published']]], $post);
    expect($post->revisions()->count())->toBe(1);

    $post = savePost($publisher, ['translations' => ['en' => ['title' => 'Second title', 'body' => body('New text'), 'status' => 'published']]], $post);
    /** @var Revision $first */
    $first = $post->revisions()->oldest('id')->firstOrFail();

    app(RestoreRevision::class)->handle($first);
    $en = $post->refresh()->translation('en');

    expect($en?->title)->toBe('First title')
        ->and(BodyRenderer::toText($en?->body))->toBe('Old text')
        ->and($en?->slug)->toBe('first-title')
        ->and($en?->status)->toBe(ContentStatus::Published)
        ->and($post->revisions()->count())->toBe(3)
        ->and($post->revisions()->latest('id')->first()?->note)->toContain('Restored');
});

it('compares texts paragraph by paragraph', function () {
    $lines = TextDiff::lines("One\nTwo\nThree", "One\nTwo and a half\nThree\nFour");

    expect($lines)->toBe([
        ['type' => 'same', 'text' => 'One'],
        ['type' => 'removed', 'text' => 'Two'],
        ['type' => 'added', 'text' => 'Two and a half'],
        ['type' => 'same', 'text' => 'Three'],
        ['type' => 'added', 'text' => 'Four'],
    ])->and(TextDiff::changed('a', 'a'))->toBeFalse();
});

it('duplicates a post as a draft in every language', function () {
    $publisher = staff(SystemRole::Publisher);
    $this->actingAs($publisher);
    $post = savePost($publisher, ['translations' => [
        'en' => ['title' => 'Harvest festival', 'status' => 'published'],
        'am' => ['title' => 'የመኸር በዓል', 'status' => 'published'],
    ]]);

    $copy = app(DuplicatePost::class)->handle($post);

    expect($copy->id)->not->toBe($post->id)
        ->and($copy->translation('en')?->title)->toBe('Harvest festival')
        ->and($copy->translation('en')?->slug)->toBe('harvest-festival-2')
        ->and($copy->translation('en')?->status)->toBe(ContentStatus::Draft)
        ->and($copy->translation('am')?->published_at)->toBeNull();
});

it('applies the post permissions of each role', function () {
    $author = staff(SystemRole::Author);
    $own = savePost($author, ['translations' => ['en' => ['title' => 'Mine']]]);
    $other = savePost(staff(SystemRole::Editor), ['translations' => ['en' => ['title' => 'Theirs']]]);

    expect($author->can('update', $own))->toBeTrue()
        ->and($author->can('update', $other))->toBeFalse()
        ->and($author->can('updateStructure', $own))->toBeTrue()
        ->and(staff(SystemRole::Translator)->can('update', $other))->toBeTrue()
        ->and(staff(SystemRole::Translator)->can('updateStructure', $other))->toBeFalse()
        ->and(staff(SystemRole::Editor)->can('delete', $other))->toBeFalse()
        ->and(staff(SystemRole::Publisher)->can('delete', $other))->toBeTrue()
        ->and(staff(SystemRole::MembershipOfficer)->can('viewAny', Post::class))->toBeFalse();
});
