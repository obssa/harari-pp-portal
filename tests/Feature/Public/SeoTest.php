<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\Redirect;
use App\Domain\Content\Support\OgImage;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use Illuminate\Support\Facades\Storage;

function livePost(string $title, string $locale = 'en'): Post
{
    $publisher = staff(SystemRole::Publisher);
    $post = app(SavePost::class)->handle(new Post, ['translations' => [$locale => ['title' => $title, 'excerpt' => 'Summary of '.$title]]], $publisher);
    app(ContentWorkflow::class)->apply($post, $locale, Transition::Publish, $publisher);

    return $post->refresh();
}

beforeEach(fn () => Storage::fake('public'));

it('describes the party and the page for search engines and sharing', function () {
    $html = (string) $this->get('/en')->getContent();

    expect($html)->toContain('<meta property="og:title"', '<meta property="og:locale" content="en_US">')
        ->toContain('<meta property="og:locale:alternate" content="am_ET">')
        ->toContain('<script type="application/ld+json" nonce="')
        ->toContain('"@type":"PoliticalParty"')
        ->toContain('<meta property="og:image" content="');
});

it('marks a post as a news article with breadcrumbs and an image', function () {
    $post = livePost('Water project opens');
    $html = (string) $this->get('/en/news/'.$post->translation('en')?->slug)->getContent();

    expect($html)->toContain('<meta property="og:type" content="article">')
        ->toContain('"@type":"NewsArticle"', '"headline":"Water project opens"', '"inLanguage":"en"')
        ->toContain('"@type":"BreadcrumbList"')
        ->toContain('/storage/og/');
});

it('generates a sharing image once per title', function () {
    $og = app(OgImage::class);

    $first = $og->url('post-1-am', 'የውሃ ፕሮጀክት ተመረቀ 2026', 'ዜና');
    $again = $og->url('post-1-am', 'የውሃ ፕሮጀክት ተመረቀ 2026', 'ዜና');
    $files = Storage::disk('public')->allFiles('og');

    expect($first)->toBe($again)->and($files)->toHaveCount(1);
    $size = getimagesizefromstring((string) Storage::disk('public')->get($files[0]));
    expect([$size[0] ?? 0, $size[1] ?? 0, $size['mime'] ?? ''])->toBe([1200, 630, 'image/png']);
});

it('lists live pages per language in sitemaps with alternates', function () {
    $post = livePost('Sitemap entry');

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(url('/sitemaps/am.xml'), escape: false);

    $this->get('/sitemaps/en.xml')
        ->assertOk()
        ->assertSee(url('/en/news/'.$post->translation('en')?->slug), escape: false)
        ->assertSee('hreflang="en"', escape: false);

    $this->get('/sitemaps/am.xml')->assertOk()->assertDontSee('sitemap-entry');
    $this->get('/sitemaps/fr.xml')->assertNotFound();
});

it('closes the whole site to robots outside production', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee("User-agent: *\nDisallow: /", escape: false)
        ->assertDontSee('Sitemap:');
});

it('redirects the old address when a published slug changes, without chains or loops', function () {
    $post = livePost('First title');
    $translation = $post->translation('en');

    $translation->update(['slug' => 'second-title']);
    $this->get('/en/news/first-title')->assertRedirect('/en/news/second-title')->assertStatus(301);

    $translation->refresh()->update(['slug' => 'third-title']);
    $this->get('/en/news/first-title')->assertRedirect('/en/news/third-title');
    $this->get('/en/news/second-title')->assertRedirect('/en/news/third-title');

    $translation->refresh()->update(['slug' => 'first-title']);
    $this->get('/en/news/first-title')->assertOk();
    expect(Redirect::query()->where('from_path', '/en/news/first-title')->exists())->toBeFalse()
        ->and(Redirect::query()->where('from_path', '/en/news/second-title')->value('hits'))->toBe(1);
});

it('does not leave redirects for drafts', function () {
    $post = app(SavePost::class)->handle(new Post, ['translations' => ['en' => ['title' => 'Draft title']]], staff(SystemRole::Editor));
    $post->translation('en')?->update(['slug' => 'renamed-draft']);

    expect(Redirect::query()->count())->toBe(0);
});

it('lets publishers manage redirects by hand', function () {
    $this->actingAs(staff(SystemRole::Publisher))->get('/admin/redirects')->assertOk();
    $this->actingAs(staff(SystemRole::Author))->get('/admin/redirects')->assertForbidden();

    Redirect::query()->create(['from_path' => '/old-page', 'to_path' => '/en/news', 'status' => 302]);
    $this->get('/old-page?utm=x')->assertRedirect('/en/news?utm=x')->assertStatus(302);
});
