<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Homepage\Actions\SaveHeroSlide;
use App\Domain\Homepage\Enums\SlideStatus;
use App\Domain\Homepage\Models\HeroSlide;
use App\Domain\Homepage\Support\SlideLink;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Media\Models\Media;
use App\Domain\Organisation\Actions\SaveLeader;
use App\Domain\Organisation\Models\Leader;
use App\Filament\Resources\HeroSlides\HeroSlideResource;
use App\Filament\Resources\HeroSlides\Pages\CreateHeroSlide;
use App\Filament\Resources\HeroSlides\Pages\EditHeroSlide;
use App\Filament\Resources\HeroSlides\Pages\ListHeroSlides;
use App\Settings\CarouselSettings;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

use function Pest\Livewire\livewire;

/**
 * @param  array<string, mixed>  $data
 */
function heroSlide(array $data): HeroSlide
{
    return app(SaveHeroSlide::class)->handle(new HeroSlide, ['layout' => 'photo', ...$data], staff(SystemRole::Publisher, mfa: false));
}

function slidePicture(): Media
{
    $path = storage_path('framework/testing/slide-'.uniqid().'.jpg');
    @mkdir(dirname($path), 0777, true);
    $image = imagecreatetruecolor(1600, 900);
    imagefill($image, 0, 0, imagecolorallocate($image, 11, 108, 168));
    imagejpeg($image, $path, 80);

    $media = app(UploadMedia::class)->handle($path, 'sample-slide.jpg', staff(SystemRole::Publisher, mfa: false));
    $media->forceFill(['alt' => ['en' => 'Sample slide picture'], 'credit' => 'Sample photographer'])->save();

    return $media;
}

it('opens the homepage with the welcome until a slide exists, then with the carousel in the editors\' order', function () {
    $this->get('/en')->assertOk()->assertDontSee('data-carousel', escape: false)->assertSee(__('site.name', [], 'en'));

    $second = heroSlide(['content' => ['en' => ['title' => 'Sample second slide']], 'sort' => 2]);
    heroSlide(['content' => ['en' => ['title' => 'Sample first slide', 'kicker' => 'Sample kicker', 'highlight' => 'first']], 'sort' => 1]);

    $this->get('/en')->assertOk()
        ->assertSee('data-carousel', escape: false)
        ->assertSee('aria-roledescription="'.__('homepage.carousel.roledescription', [], 'en').'"', escape: false)
        ->assertSeeInOrder(['id="hero-slide-1"', 'Sample <span class="text-gold-400">first</span> slide', 'id="hero-slide-2"', 'Sample second slide'], escape: false)
        ->assertSee(__('homepage.carousel.go_to', ['number' => 2, 'title' => 'Sample second slide'], 'en'))
        ->assertSee('data-controls', escape: false)
        ->assertSee(__('homepage.carousel.pause', [], 'en'));

    $second->forceFill(['sort' => 0])->save();
    $this->get('/en')->assertSeeInOrder(['Sample second slide', 'Sample first slide']);
});

it('shows a slide only while it is on and within its dates, in its languages, with the fallback chain', function () {
    heroSlide(['content' => ['en' => ['title' => 'Sample English slide'], 'am' => ['title' => 'የናሙና ስላይድ']]]);
    heroSlide(['content' => ['en' => ['title' => 'Sample switched off']], 'active' => false]);
    heroSlide(['content' => ['en' => ['title' => 'Sample not yet']], 'starts_at' => now()->addDay()->toDateTimeString()]);
    heroSlide(['content' => ['en' => ['title' => 'Sample over']], 'starts_at' => now()->subDays(3)->toDateTimeString(), 'ends_at' => now()->subDay()->toDateTimeString()]);
    heroSlide(['content' => ['en' => ['title' => 'Sample Oromo only']], 'locales' => ['om']]);

    $this->get('/en')->assertSee('Sample English slide')
        ->assertDontSee('Sample switched off')->assertDontSee('Sample not yet')->assertDontSee('Sample over')->assertDontSee('Sample Oromo only');
    $this->get('/har')->assertSee('<h2 lang="am"', escape: false)->assertSee('የናሙና ስላይድ');
    $this->get('/om')->assertSee('Sample Oromo only')->assertSee('Sample English slide');

    $statuses = HeroSlide::query()->orderBy('id')->get()->map(fn (HeroSlide $slide): SlideStatus => $slide->status())->all();
    expect($statuses)->toBe([SlideStatus::Live, SlideStatus::Off, SlideStatus::Scheduled, SlideStatus::Ended, SlideStatus::Live]);
});

it('checks a slide before saving it', function (array $data, string $field) {
    expect(fn () => heroSlide($data))->toThrow(function (ValidationException $e) use ($field): void {
        expect($e->errors())->toHaveKey($field);
    });
})->with([
    'no layout' => [['layout' => 'video', 'content' => ['en' => ['title' => 'Sample']]], 'data.layout'],
    'no heading' => [['content' => ['en' => ['text' => 'Sample text only']]], 'data.content.en.title'],
    'banner without a picture' => [['layout' => 'banner', 'content' => ['en' => ['title' => 'Sample']]], 'data.image_id'],
    'ends before it starts' => [['content' => ['en' => ['title' => 'Sample']], 'starts_at' => '2026-11-02 10:00', 'ends_at' => '2026-11-01 10:00'], 'data.ends_at'],
    'script address' => [['content' => ['en' => ['title' => 'Sample']], 'buttons' => [['kind' => 'url', 'url' => 'javascript:alert(1)', 'label' => ['en' => 'Go']]]], 'data.buttons.0.kind'],
    'button without a label' => [['content' => ['en' => ['title' => 'Sample']], 'buttons' => [['kind' => 'route', 'route' => 'news.index']]], 'data.buttons.0.label.en'],
]);

it('sends buttons only to site pages, profiles and safe addresses', function () {
    $leader = app(SaveLeader::class)->handle(new Leader, ['body' => 'secretariat', 'translations' => ['en' => ['name' => 'Sample Slide Leader', 'published' => true]]], staff(SystemRole::Publisher, mfa: false));

    expect(SlideLink::isValid('route:membership.apply'))->toBeTrue()
        ->and(SlideLink::isValid('route:admin.dashboard'))->toBeFalse()
        ->and(SlideLink::isValid('url:https://example.org/sample'))->toBeTrue()
        ->and(SlideLink::isValid('url:/en/news'))->toBeTrue()
        ->and(SlideLink::isValid('url://evil.example'))->toBeFalse()
        ->and(SlideLink::isValid('url:javascript:alert(1)'))->toBeFalse()
        ->and(SlideLink::url('route:membership.apply', 'am'))->toBe(url('/am/membership/apply'))
        ->and(SlideLink::url("leader:{$leader->id}", 'en'))->toBe(url('/en/leaders/sample-slide-leader'))
        ->and(SlideLink::url('leader:missing', 'en'))->toBeNull();

    heroSlide(['content' => ['en' => ['title' => 'Sample buttons']], 'buttons' => [
        ['kind' => 'route', 'route' => 'membership.apply', 'style' => 'primary', 'label' => ['en' => 'Sample join']],
        ['kind' => 'leader', 'leader' => $leader->id, 'style' => 'secondary', 'label' => ['en' => 'Sample profile']],
        ['kind' => 'url', 'url' => 'https://example.org/sample', 'style' => 'secondary', 'label' => ['en' => 'Sample away']],
    ]]);

    $this->get('/en')
        ->assertSee('href="'.url('/en/membership/apply').'"', escape: false)
        ->assertSee('href="'.url('/en/leaders/sample-slide-leader').'"', escape: false)
        ->assertSee('rel="noopener"', escape: false);
});

it('draws the banner and the portrait layouts', function () {
    $picture = slidePicture();
    heroSlide(['layout' => 'banner', 'image_id' => $picture->id, 'content' => ['en' => [
        'title' => 'Prosperity Party', 'highlight' => 'ብልፅግና', 'text' => 'Sample tagline.', 'note' => 'Sample slogan ahead',
    ]], 'buttons' => [['kind' => 'route', 'route' => 'membership.apply', 'label' => ['en' => 'Join now']]]]);
    heroSlide(['layout' => 'portrait', 'image_id' => $picture->id, 'content' => ['en' => [
        'kicker' => 'Sample kicker', 'title' => 'Sample message on a firm foundation', 'highlight' => 'on a firm foundation',
        'note' => "H.E Sample Person\nHead of the sample party",
    ]]]);

    $this->get('/en')->assertOk()
        ->assertSee('<span class="sr-only">Prosperity Party ብልፅግና</span>', escape: false)
        ->assertSee('>PROSPERITY</text>', escape: false)
        ->assertSee('Sample tagline.')
        ->assertSee('<span class="sr-only">Sample slogan ahead</span>', escape: false)
        ->assertSee('>ahead</span>', escape: false)
        ->assertSee('clip-path:polygon(0_0,81%_0,100%_100%,19%_100%)', escape: false)
        ->assertSee('Sample message <span class="text-gold-400">on a firm foundation</span>', escape: false)
        ->assertSeeInOrder(['H.E Sample Person', 'Head of the sample party'])
        ->assertSee('Sample slide picture');
});

it('follows the carousel settings', function () {
    heroSlide(['content' => ['en' => ['title' => 'Sample one']], 'duration' => 12]);
    heroSlide(['content' => ['en' => ['title' => 'Sample two']]]);

    $settings = app(CarouselSettings::class);
    $settings->interval = 9;
    $settings->transition = 'focus';
    $settings->autoplay = false;
    $settings->show_dots = false;
    $settings->save();

    $this->get('/en')
        ->assertSee('data-interval="9000"', escape: false)
        ->assertSee('data-autoplay="false"', escape: false)
        ->assertSee('data-mode="focus"', escape: false)
        ->assertSee('data-duration="12000"', escape: false)
        ->assertDontSee('data-dot=', escape: false)
        ->assertSee('data-prev', escape: false);
});

it('previews any slide through a signed link only', function () {
    $slide = heroSlide(['content' => ['en' => ['title' => 'Sample hidden slide']], 'active' => false]);

    $this->get('/en')->assertDontSee('Sample hidden slide');
    $this->get(URL::temporarySignedRoute('preview.slide', now()->addHour(), ['slide' => $slide->id, 'language' => 'en']))
        ->assertOk()->assertSee('Sample hidden slide')->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee(SlideStatus::Off->label());
    $this->get("/preview/slides/{$slide->id}/en")->assertForbidden();
});

it('gives the carousel manager to those who arrange the homepage', function () {
    heroSlide(['content' => ['en' => ['title' => 'Sample managed slide']]]);

    $this->actingAs(staff(SystemRole::Author, mfa: false));
    $this->get(HeroSlideResource::getUrl('index'))->assertForbidden();

    $this->actingAs(staff(SystemRole::Publisher, mfa: false));
    $this->get(HeroSlideResource::getUrl('index'))->assertOk()
        ->assertSee('Sample managed slide')
        ->assertSee(__('admin.carousel.settings'))
        ->assertSee(SlideStatus::Live->label());
});

it('creates, edits, duplicates and reorders slides in the admin, and saves the settings', function () {
    $this->actingAs(staff(SystemRole::Publisher, mfa: false));

    livewire(CreateHeroSlide::class)
        ->fillForm([
            'layout' => 'photo',
            'content' => ['en' => ['title' => 'Sample admin slide'], 'am' => ['title' => 'የናሙና የአስተዳደር ስላይድ']],
            'buttons' => [['kind' => 'route', 'route' => 'news.index', 'style' => 'primary', 'label' => ['en' => 'Sample news']]],
            'active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $slide = HeroSlide::query()->firstOrFail();
    expect($slide->name)->toBe('Sample admin slide')
        ->and($slide->buttons)->toEqual([['link' => 'route:news.index', 'style' => 'primary', 'label' => ['en' => 'Sample news']]])
        ->and($slide->languages())->toBe(['en', 'am']);

    livewire(EditHeroSlide::class, ['record' => $slide->getRouteKey()])
        ->assertFormSet(['content.en.title' => 'Sample admin slide', 'layout' => 'photo'])
        ->fillForm(['content.en.title' => 'Sample renamed slide', 'duration' => 10])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($slide->fresh()?->text('title', 'en')[0])->toBe('Sample renamed slide')
        ->and($slide->fresh()?->duration)->toBe(10)
        ->and($slide->fresh()?->buttons)->toEqual([['link' => 'route:news.index', 'style' => 'primary', 'label' => ['en' => 'Sample news']]])
        ->and(SaveHeroSlide::decompose("leader:{$slide->id}"))->toBe(['kind' => 'leader', 'leader' => (string) $slide->id]);

    livewire(ListHeroSlides::class)->callTableAction('duplicate', $slide);
    $copy = HeroSlide::query()->whereKeyNot($slide->id)->firstOrFail();
    expect($copy->active)->toBeFalse()->and($copy->name)->toBe(__('admin.carousel.copy_name', ['name' => 'Sample admin slide']));

    livewire(ListHeroSlides::class)->call('reorderTable', [(string) $copy->id, (string) $slide->id]);
    expect(HeroSlide::query()->orderBy('sort')->pluck('id')->all())->toBe([$copy->id, $slide->id]);

    livewire(ListHeroSlides::class)
        ->callAction('carouselSettings', data: ['autoplay' => true, 'interval' => 5, 'transition' => 'focus', 'height' => 'tall', 'pause_on_hover' => true, 'show_arrows' => true, 'show_dots' => true, 'show_progress' => false, 'include_stories' => false])
        ->assertHasNoActionErrors();
    expect(CarouselSettings::current())->toMatchArray(['interval' => 5, 'transition' => 'focus', 'height' => 'tall', 'show_progress' => false, 'include_stories' => false]);
});
