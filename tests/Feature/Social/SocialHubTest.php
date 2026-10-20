<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Homepage\Enums\SectionKey;
use App\Domain\Homepage\Models\HomepageSection;
use App\Domain\Organisation\Actions\SaveOffice;
use App\Domain\Organisation\Models\Office;
use App\Domain\Social\Enums\Platform;
use App\Domain\Social\Models\SocialPost;
use App\Domain\Social\Models\SocialSource;
use Carbon\CarbonImmutable;

/**
 * @param  array<string, mixed>  $data
 */
function hubPost(SocialSource $source, string $text, string $at, array $data = []): SocialPost
{
    /** @var SocialPost */
    return $source->posts()->create([
        'external_id' => (string) random_int(1, PHP_INT_MAX), 'posted_at' => CarbonImmutable::parse($at), 'text' => $text,
        'permalink' => $source->url().'/'.random_int(1, 9999), ...$data,
    ]);
}

it('shows the visible posts, pinned first, with filters and the accounts to follow', function () {
    $office = app(SaveOffice::class)->handle(new Office, ['type' => 'woreda', 'translations' => ['en' => ['name' => 'Sample woreda office', 'published' => true]]], staff(SystemRole::Publisher));
    $telegram = SocialSource::query()->create(['platform' => Platform::Telegram, 'handle' => 'samplechannel', 'name' => 'Sample branch channel']);
    $youtube = SocialSource::query()->create(['platform' => Platform::YouTube, 'handle' => 'UCabcdefghijklmnopqrstuv', 'name' => 'Sample woreda videos', 'office_id' => $office->id]);
    $off = SocialSource::query()->create(['platform' => Platform::X, 'handle' => 'sampleoff', 'name' => 'Sample switched off', 'enabled' => false]);

    hubPost($telegram, 'Sample older pinned post', '2026-10-10 08:00', ['is_pinned' => true]);
    hubPost($telegram, 'Sample newest post', '2026-10-19 08:00');
    hubPost($youtube, 'Sample woreda video', '2026-10-18 08:00', ['has_video' => true]);
    hubPost($telegram, 'Sample hidden post', '2026-10-19 09:00', ['is_hidden' => true]);
    hubPost($off, 'Sample post of a switched-off account', '2026-10-19 10:00');

    $html = (string) $this->get('/en/social')->assertOk()
        ->assertSee('Sample newest post')->assertSee('Sample woreda video')
        ->assertDontSee('Sample hidden post')->assertDontSee('Sample post of a switched-off account')
        ->assertSee('https://t.me/samplechannel', escape: false)
        ->assertSee('data:image/svg+xml;base64,', escape: false)
        ->assertSee(__('social.hub.open_on', ['platform' => 'Telegram'], 'en'))
        ->getContent();
    expect(strpos($html, 'Sample older pinned post'))->toBeLessThan(strpos($html, 'Sample newest post'));

    $this->get('/en/social?platform=youtube')->assertSee('Sample woreda video')->assertDontSee('Sample newest post');
    $this->get('/en/social?office='.$office->id)->assertSee('Sample woreda video')->assertDontSee('Sample newest post');

    // The office page shows its own account's posts.
    $this->get('/en/offices/sample-woreda-office')->assertSee('Sample woreda video')->assertDontSee('Sample newest post');
});

it('shows the latest posts on the homepage and lists the hub in the sitemaps', function () {
    $telegram = SocialSource::query()->create(['platform' => Platform::Telegram, 'handle' => 'samplechannel', 'name' => 'Sample channel']);
    HomepageSection::query()->where('key', SectionKey::Social->value)->update(['enabled' => true]);

    $this->get('/en')->assertDontSee('id="home-social"', escape: false);
    hubPost($telegram, 'Sample homepage social post', '2026-10-19 08:00');
    $this->get('/en')->assertSee('id="home-social"', escape: false)->assertSee('Sample homepage social post');

    $this->get('/sitemaps/en.xml')->assertSee(url('/en/social'), escape: false);
});
