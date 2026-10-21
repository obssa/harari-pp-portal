<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\LoadContentPack;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentPackItem;
use App\Domain\Content\Models\Post;
use App\Domain\Events\Models\Event;
use App\Domain\Homepage\Enums\SectionKey;
use App\Domain\Homepage\Models\HomepageSection;
use App\Domain\Import\Models\ContentImport;
use App\Domain\Import\Models\ContentSource;
use App\Domain\Organisation\Actions\SaveLeader;
use App\Domain\Organisation\Enums\LeaderBody;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Settings\SiteSettings;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

/**
 * A pack file with the given records, in a folder of its own.
 *
 * @param  array<string, mixed>  $records
 */
function samplePack(array $records): string
{
    $folder = storage_path('framework/testing/pack-'.uniqid());
    File::ensureDirectoryExists($folder);
    file_put_contents("{$folder}/pack.json", json_encode(['version' => 1, ...$records]));

    return "{$folder}/pack.json";
}

it('loads the real content pack, published, with portraits, heads, pages and the homepage', function () {
    staff(SystemRole::SuperAdmin, mfa: false);

    $counts = app(LoadContentPack::class)->handle(database_path('content/pack.json'));

    expect($counts)->toMatchArray(['leagues' => 2, 'offices' => 10, 'leaders' => 14, 'pages' => 3, 'events' => 1, 'homepage' => 2, 'settings' => 1]);

    $head = Leader::query()->whereHas('translations', fn ($q) => $q->where('name', 'Getu Weyesa'))->firstOrFail();
    expect($head->body)->toBe(LeaderBody::Secretariat)
        ->and($head->source_url)->toBe('https://t.me/hrprosperityparty/23920')
        ->and($head->portrait?->alt)->toHaveKeys(['en', 'am', 'om'])
        ->and($head->portrait?->credit)->toBe('t.me')
        ->and($head->translation('am', fallback: false)?->status)->toBe(ContentStatus::Published)
        ->and(Office::query()->where('type', 'branch')->value('head_leader_id'))->toBe($head->id)
        ->and(Office::query()->where('type', 'woreda')->count())->toBe(9)
        ->and(League::query()->whereNotNull('head_leader_id')->count())->toBe(1)
        ->and(Leader::query()->where('body', LeaderBody::Government->value)->count())->toBe(7)
        ->and(Event::query()->count())->toBe(1);

    $leadership = HomepageSection::query()->where('key', SectionKey::Leadership->value)->firstOrFail();
    expect($leadership->enabled)->toBeTrue()
        ->and($leadership->setting('leader_id'))->toBe($head->id)
        ->and(HomepageSection::query()->where('key', SectionKey::Counters->value)->firstOrFail()->setting('items'))->toHaveCount(4)
        ->and(app(SiteSettings::class)->social_links['telegram'] ?? null)->toBe('https://t.me/hrprosperityparty');

    $this->get('/en/about')->assertOk()->assertSee('About the branch')->assertSee('https://t.me/hrprosperityparty/23920', escape: false);
    $this->get('/am/about/history')->assertOk()->assertSee('ሀረሪ ብሔራዊ ሊግ');
    $this->get('/om/about/harar')->assertOk()->assertSee('Harar Jugol');
    $this->get('/en/leaders')->assertOk()->assertSee('Getu Weyesa')->assertSee(LeaderBody::Government->label('en'));
    $this->get('/en/leaders/'.$head->translation('en')?->slug)->assertOk()
        ->assertSee('href="https://t.me/hrprosperityparty/23920"', escape: false);
});

it('loads each record once and never brings back what an editor deleted', function () {
    staff(SystemRole::SuperAdmin, mfa: false);
    $path = samplePack(['leagues' => [
        ['key' => 'league.sample', 'translations' => ['en' => ['name' => 'Sample Wing', 'summary' => 'Sample summary.']]],
    ]]);

    expect(app(LoadContentPack::class)->handle($path))->toBe(['leagues' => 1]);
    League::query()->firstOrFail()->delete();

    expect(app(LoadContentPack::class)->handle($path))->toBe([])
        ->and(League::query()->count())->toBe(0)
        ->and(ContentPackItem::query()->where('key', 'league.sample')->exists())->toBeTrue();
});

it('keeps the social links and homepage an editor already set', function () {
    staff(SystemRole::SuperAdmin, mfa: false);
    $settings = app(SiteSettings::class);
    $settings->social_links = ['telegram' => 'https://t.me/sample_channel'];
    $settings->save();

    app(LoadContentPack::class)->handle(samplePack(['settings' => ['social_links' => ['telegram' => 'https://t.me/hrprosperityparty', 'facebook' => 'https://facebook.com/sample']]]));

    expect(app(SiteSettings::class)->social_links)->toBe(['telegram' => 'https://t.me/sample_channel', 'facebook' => 'https://facebook.com/sample']);
});

it('publishes the stories imported from the branch before it was trusted, once, with their dates', function () {
    staff(SystemRole::SuperAdmin, mfa: false);
    $source = ContentSource::query()->where('handle', 'hrprosperityparty')->firstOrFail();
    $post = new Post(['type' => 'news', 'source_locale' => 'am']);
    $post->forceFill(['source_url' => 'https://t.me/hrprosperityparty/1', 'source_name' => $source->name])->save();
    $post->putTranslation('am', ['title' => 'የናሙና ዜና', 'status' => ContentStatus::Draft]);
    ContentImport::query()->create(['content_source_id' => $source->id, 'external_id' => 'hrprosperityparty/1', 'message_at' => '2026-10-01 09:00:00', 'status' => ContentImport::IMPORTED, 'post_id' => $post->id]);

    expect(app(LoadContentPack::class)->handle(samplePack([])))->toBe(['stories' => 1]);
    $translation = $post->fresh()?->translation('am', fallback: false);
    expect($translation?->status)->toBe(ContentStatus::Published)
        ->and($translation?->published_at?->toDateTimeString())->toBe('2026-10-01 09:00:00');

    $translation?->forceFill(['status' => ContentStatus::Draft])->save();
    expect(app(LoadContentPack::class)->handle(samplePack([])))->toBe([])
        ->and($post->fresh()?->translation('am', fallback: false)?->status)->toBe(ContentStatus::Draft);
});

it('waits for a Super Admin and a pack file without failing a deploy', function () {
    $this->artisan('content:load-pack')->expectsOutputToContain('No Super Admin yet')->assertSuccessful();

    staff(SystemRole::SuperAdmin, mfa: false);
    $this->artisan('content:load-pack', ['--path' => storage_path('framework/testing/none.json')])->expectsOutputToContain('No content pack')->assertSuccessful();
    $this->artisan('content:load-pack', ['--path' => samplePack([])])->expectsOutputToContain('nothing new')->assertSuccessful();
});

it('accepts only web addresses as a leader source', function () {
    $publisher = staff(SystemRole::Publisher, mfa: false);
    $data = ['body' => 'office_heads', 'source_url' => 'javascript:alert(1)', 'translations' => ['en' => ['name' => 'Sample Person']]];

    expect(fn () => app(SaveLeader::class)->handle(new Leader, $data, $publisher))->toThrow(ValidationException::class);

    $leader = app(SaveLeader::class)->handle(new Leader, [...$data, 'source_url' => 'https://example.org/sample', 'source_date' => '2026-10-15'], $publisher);
    expect($leader->source_url)->toBe('https://example.org/sample')
        ->and($leader->source_date?->toDateString())->toBe('2026-10-15');
});

it('lists the about page and its pages in the default menu once they are published', function () {
    staff(SystemRole::SuperAdmin, mfa: false);
    app(LoadContentPack::class)->handle(samplePack(['pages' => [
        ['key' => 'page.about', 'translations' => ['en' => ['title' => 'About the sample branch', 'slug' => 'about', 'excerpt' => 'Sample excerpt.']]],
        ['key' => 'page.history', 'parent' => 'page.about', 'translations' => ['en' => ['title' => 'Sample history', 'slug' => 'history']]],
    ]]));

    $html = (string) $this->get('/en/news')->getContent();
    expect($html)->toContain('About the sample branch', 'Sample history', '/en/about/history');
});
