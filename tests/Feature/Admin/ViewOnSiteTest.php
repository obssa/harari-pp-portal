<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePage;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Domain\Organisation\Actions\SaveLeader;
use App\Domain\Organisation\Models\Leader;
use App\Filament\Pages\ManageHomepage;
use App\Filament\Resources\Leaders\LeaderResource;
use App\Filament\Resources\Posts\PostResource;
use App\Support\PublicLinks;

function viewOnSitePost(array $publishIn): Post
{
    $publisher = staff(SystemRole::Publisher, mfa: false);
    $post = app(SavePost::class)->handle(new Post, ['type' => 'news', 'translations' => [
        'en' => ['title' => 'Sample story to view'],
        'am' => ['title' => 'የናሙና ዜና ለማየት'],
    ]], $publisher);
    foreach ($publishIn as $locale) {
        app(ContentWorkflow::class)->apply($post, $locale, Transition::Publish, $publisher);
    }

    return $post->refresh();
}

it('knows the public address of each published language, and none for drafts', function () {
    $post = viewOnSitePost(['en']);
    $draft = viewOnSitePost([]);

    expect(PublicLinks::for($post))->toBe(['en' => url('/en/news/sample-story-to-view')])
        ->and(PublicLinks::for($draft))->toBe([])
        ->and(PublicLinks::preferred($post, 'am'))->toBe(url('/en/news/sample-story-to-view'));

    $publisher = staff(SystemRole::Publisher, mfa: false);
    $parent = app(SavePage::class)->handle(new Page, ['translations' => ['en' => ['title' => 'Sample about', 'slug' => 'sample-about', 'published' => true]]], $publisher);
    $child = app(SavePage::class)->handle(new Page, ['parent_id' => $parent->id, 'translations' => ['en' => ['title' => 'Sample history', 'slug' => 'history', 'published' => true]]], $publisher);
    expect(PublicLinks::for($child))->toBe(['en' => url('/en/sample-about/history')]);

    $leader = app(SaveLeader::class)->handle(new Leader, ['body' => 'secretariat', 'translations' => [
        'en' => ['name' => 'Sample Leader', 'published' => true],
        'am' => ['name' => 'ናሙና መሪ', 'published' => false],
    ]], $publisher);
    expect(array_keys(PublicLinks::for($leader)))->toBe(['en']);
});

it('puts View on site on the edit page, with the published languages, or says the item is not on the site yet', function () {
    $this->actingAs(staff(SystemRole::Publisher, mfa: false));
    $post = viewOnSitePost(['en']);
    $draft = viewOnSitePost([]);

    $this->get(PostResource::getUrl('edit', ['record' => $post]))->assertOk()
        ->assertSee(__('admin.view_on_site.label'))
        ->assertSee('href="'.url('/en/news/sample-story-to-view').'"', escape: false)
        ->assertSee('target="_blank"', escape: false);

    $this->get(PostResource::getUrl('edit', ['record' => $draft]))->assertOk()
        ->assertSee(__('admin.view_on_site.label'))
        ->assertSee(e(__('admin.view_on_site.draft_post')), escape: false);
});

it('offers View on site on list rows and View the site on pages that change the whole site', function () {
    $this->actingAs(staff(SystemRole::Publisher, mfa: false));
    $publisher = staff(SystemRole::Publisher, mfa: false);
    app(SaveLeader::class)->handle(new Leader, ['body' => 'secretariat', 'translations' => ['en' => ['name' => 'Sample Listed Leader', 'published' => true]]], $publisher);

    $this->get(LeaderResource::getUrl('index'))->assertOk()
        ->assertSee('/en/leaders/sample-listed-leader', escape: false);
    $this->get(ManageHomepage::getUrl())->assertOk()
        ->assertSee(__('admin.view_on_site.site'))
        ->assertSee('href="'.url('/en').'"', escape: false);
});
