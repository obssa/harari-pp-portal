<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\LoadContentPack;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Domain\Organisation\Models\League;
use App\Support\HararPhotos;
use Illuminate\Support\Facades\File;

it('ships every photo of Harar in each size and format, with its credit', function () {
    foreach (HararPhotos::names() as $name) {
        $photo = HararPhotos::get($name);
        foreach ($photo['widths'] as $width) {
            expect(public_path("images/harar/{$name}-{$width}.avif"))->toBeFile()
                ->and(public_path("images/harar/{$name}-{$width}.webp"))->toBeFile();
        }
        expect(public_path(ltrim(HararPhotos::fallback($name), '/')))->toBeFile()
            ->and(HararPhotos::credit($name))->toContain($photo['author'], $photo['licence'])
            ->and($photo['source'])->toStartWith('https://commons.wikimedia.org/wiki/File:')
            ->and(__("harar.photo.{$name}"))->not->toBe("harar.photo.{$name}");
    }
});

it('opens the homepage on Harar Gate and shows Harar with credited photos', function () {
    $html = (string) $this->get('/en')->assertOk()->getContent();

    expect($html)->toContain('/images/harar/gate-1600.avif', 'fetchpriority="high"', HararPhotos::get('gate')['source'])
        ->toContain('/images/harar/interior-1024.avif', __('content.photo_credit', ['credit' => HararPhotos::credit('interior')]))
        ->toContain(__('homepage.heritage.fact_mosques'));
});

it('gives a story without a photo the party placeholder and keeps every card the same shape', function () {
    $publisher = staff(SystemRole::Publisher, mfa: false);
    $post = app(SavePost::class)->handle(new Post, ['type' => 'speech', 'translations' => ['en' => ['title' => 'Sample speech without a photo']]], $publisher);
    app(ContentWorkflow::class)->apply($post, 'en', Transition::Publish, $publisher);

    $html = (string) $this->get('/en/news')->assertOk()->getContent();

    expect($html)->toContain('Sample speech without a photo', 'from-magenta-600', 'line-clamp-3', 'aspect-[16/10]');
});

it('loads the Harar photos onto the pages and the wings their colours and work, filling only what is empty', function () {
    staff(SystemRole::SuperAdmin, mfa: false);
    app(LoadContentPack::class)->handle(database_path('content/pack.json'));

    $about = Page::query()->whereHas('translations', fn ($q) => $q->where('slug', 'about'))->firstOrFail();
    expect($about->template)->toBe('landing')
        ->and($about->featuredImage?->credit)->toBe('Giustino, CC BY 2.0, Wikimedia Commons');

    $women = League::query()->whereHas('translations', fn ($q) => $q->where('name', "Women's Wing"))->firstOrFail();
    expect($women->accentTone())->toBe('magenta');

    $this->get('/en/leagues/'.$women->translation('en')?->slug)->assertOk()
        ->assertSee('bg-magenta-700', escape: false)
        ->assertSee('Zinet Yusuf')
        ->assertSee('Resurgence Day')
        ->assertSee('https://t.me/hrprosperityparty/23217', escape: false);
    $this->get('/en/about')->assertOk()->assertSee(__('content.photo_credit', ['credit' => 'Giustino, CC BY 2.0, Wikimedia Commons']));
    $this->get('/en/offices/harari-region-branch-office')->assertOk()->assertSee('Amir-Nur Woreda')->assertSee('H.E Getu Wayyesa Tola');
});

it('keeps a colour an editor chose when the pack adds a wing profile later', function () {
    staff(SystemRole::SuperAdmin, mfa: false);
    $folder = storage_path('framework/testing/pack-'.uniqid());
    File::ensureDirectoryExists($folder);
    $league = ['key' => 'league.sample', 'translations' => ['en' => ['name' => 'Sample Wing', 'summary' => 'Sample summary.']]];
    file_put_contents("{$folder}/pack.json", json_encode(['leagues' => [$league]]));
    app(LoadContentPack::class)->handle("{$folder}/pack.json");

    League::query()->firstOrFail()->forceFill(['accent' => 'night'])->save();
    $body = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Sample work of the wing.']]]]];
    file_put_contents("{$folder}/pack.json", json_encode(['leagues' => [$league], 'updates' => [
        ['key' => 'league.sample#profile', 'target' => 'league.sample', 'accent' => 'magenta', 'body' => ['en' => $body]],
    ]]));

    expect(app(LoadContentPack::class)->handle("{$folder}/pack.json"))->toBe(['updates' => 1]);
    $saved = League::query()->firstOrFail();
    expect($saved->accent)->toBe('night')
        ->and($saved->translation('en')?->getAttribute('body'))->toEqual($body)
        ->and(app(LoadContentPack::class)->handle("{$folder}/pack.json"))->toBe([]);
});
