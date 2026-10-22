<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Http\Controllers\Public\PreviewController;

/**
 * @param  array<string, array<string, mixed>>  $translations
 * @param  list<string>  $publish
 */
function newsPost(array $translations, array $publish = [], string $type = 'news'): Post
{
    $publisher = staff(SystemRole::Publisher);
    $post = app(SavePost::class)->handle(new Post, ['type' => $type, 'translations' => $translations], $publisher);
    foreach ($publish as $locale) {
        app(ContentWorkflow::class)->apply($post, $locale, Transition::Publish, $publisher);
    }

    return $post->refresh();
}

function newsBody(string ...$paragraphs): array
{
    return [
        'type' => 'doc',
        'content' => array_map(fn (string $text): array => ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]], $paragraphs),
    ];
}

it('lists live posts of the page language only', function () {
    newsPost(['en' => ['title' => 'Live English news', 'excerpt' => 'Summary']], ['en']);
    newsPost(['en' => ['title' => 'Draft English news']]);
    newsPost(['om' => ['title' => 'Oduu Afaan Oromoo']], ['om']);

    $this->get('/en/news')
        ->assertOk()
        ->assertSee('Live English news')
        ->assertSee('Summary')
        ->assertDontSee('Draft English news')
        ->assertDontSee('Oduu Afaan Oromoo');
});

it('lists Amharic-only posts on the English and Afaan Oromo pages, marked as Amharic', function () {
    newsPost(['am' => ['title' => 'የቅርንጫፉ የናሙና ዜና']], ['am']);

    foreach (['/en/news', '/om/news'] as $url) {
        $this->get($url)->assertOk()->assertSee('የቅርንጫፉ የናሙና ዜና')->assertSee('lang="am"', escape: false)
            ->assertSee(__('news.in_language', ['language' => 'አማርኛ'], substr($url, 1, 2)));
    }
});

it('shows Amharic posts on the Harari pages, marked as Amharic', function () {
    newsPost(['am' => ['title' => 'የአማርኛ ዜና']], ['am']);

    $this->get('/har/news')
        ->assertOk()
        ->assertSee('የአማርኛ ዜና')
        ->assertSee('<article lang="am"', escape: false)
        ->assertSee('/am/news/', escape: false);
});

it('filters the listing by type', function () {
    newsPost(['en' => ['title' => 'A statement']], ['en'], 'statement');
    newsPost(['en' => ['title' => 'A speech']], ['en'], 'speech');

    $this->get('/en/news?type=statement')
        ->assertOk()
        ->assertSee('A statement')
        ->assertDontSee('A speech');
});

it('shows a post with its body, dates, reading time and links to other languages', function () {
    $post = newsPost([
        'en' => ['title' => 'Council session', 'kicker' => 'Harar', 'body' => newsBody('First paragraph.', 'Second paragraph.')],
        'am' => ['title' => 'የምክር ቤት ጉባኤ'],
        'om' => ['title' => 'Walgahii mana maree'],
    ], ['en', 'am']);
    $en = $post->translation('en');

    $response = $this->get('/en/news/'.$en?->slug)
        ->assertOk()
        ->assertSee('Council session')
        ->assertSee('First paragraph.')
        ->assertSee('E.C.')
        ->assertSee(trans_choice('news.reading_time', 1, ['minutes' => 1], 'en'));

    $html = (string) $response->getContent();
    expect($html)->toContain('hreflang="am" href="'.url('/am/news/'.$post->translation('am')?->slug).'"')
        ->toContain('hreflang="om" href="'.url('/om/news').'"')
        ->toContain(__('news.also_available', [], 'en'));
});

it('answers 404 for drafts, unknown slugs and slugs of another language', function () {
    $post = newsPost(['en' => ['title' => 'Draft only']]);
    $live = newsPost(['am' => ['title' => 'የታተመ']], ['am']);

    $this->get('/en/news/'.$post->translation('en')?->slug)->assertNotFound();
    $this->get('/en/news/no-such-post')->assertNotFound();
    $this->get('/en/news/'.$live->translation('am')?->slug)->assertNotFound();
});

it('lets public news pages be cached and keeps them free of session cookies', function () {
    $post = newsPost(['en' => ['title' => 'Cacheable']], ['en']);

    $response = $this->withUnencryptedCookie('hpp_locale', 'en')->get('/en/news/'.$post->translation('en')?->slug)->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('s-maxage')
        ->and($response->headers->getCookies())->toBe([]);
});

it('publishes RSS and JSON feeds per language', function () {
    newsPost(['en' => ['title' => 'Feed item', 'excerpt' => 'Feed summary', 'body' => newsBody('Feed body')]], ['en']);
    newsPost(['en' => ['title' => 'Unpublished item']]);

    $this->get('/en/feed.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
        ->assertSee('<title>Feed item</title>', escape: false)
        ->assertSee('<language>en</language>', escape: false)
        ->assertDontSee('Unpublished item');

    $this->get('/en/feed.json')
        ->assertOk()
        ->assertJsonPath('version', 'https://jsonfeed.org/version/1.1')
        ->assertJsonPath('items.0.title', 'Feed item')
        ->assertJsonPath('items.0.summary', 'Feed summary')
        ->assertJsonCount(1, 'items');
});

it('shows drafts through a signed preview link only', function () {
    $post = newsPost(['en' => ['title' => 'Secret draft']]);

    $this->get(PreviewController::url($post, 'en'))
        ->assertOk()
        ->assertSee('Secret draft')
        ->assertSee(__('news.preview_notice', [], 'en'))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $this->get(route('preview.post', ['post' => $post->id, 'language' => 'en']))->assertForbidden();
});
