<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Workflow\ContentWorkflow;
use App\Domain\Content\Workflow\Transition;
use App\Domain\Events\Actions\SaveEvent;
use App\Domain\Events\Models\Event;
use App\Domain\Forms\Actions\SaveForm;
use App\Domain\Forms\Models\Form;
use App\Domain\Media\Actions\UploadMedia;
use App\Domain\Organisation\Actions\SaveLeader;
use App\Domain\Organisation\Models\Leader;
use App\Filament\Pages\ManagePressKit;
use App\Settings\PressKitSettings;
use Carbon\CarbonImmutable;
use Tests\Support\TestFiles;

use function Pest\Livewire\livewire;

it('shows the press kit with the boilerplate, contact, logos, colours, portraits and releases', function () {
    $logo = app(UploadMedia::class)->handle(TestFiles::svg('<rect width="10" height="10" fill="#3151ed"/>'), 'logo.svg');
    $portrait = app(UploadMedia::class)->handle(TestFiles::image(300, 300), 'leader.jpg');
    app(SaveLeader::class)->handle(new Leader, ['body' => 'executive_committee', 'portrait_id' => $portrait->id,
        'translations' => ['en' => ['name' => 'Sample Chairperson', 'published' => true]]], staff(SystemRole::Publisher));
    $press = app(SaveForm::class)->handle(new Form, ['type' => 'press', 'translations' => ['en' => ['title' => 'Sample press enquiry', 'published' => true]]], staff(SystemRole::Publisher));
    $publisher = staff(SystemRole::Publisher);
    $release = app(SavePost::class)->handle(new Post, ['type' => 'press_release', 'translations' => ['en' => ['title' => 'Sample press release']]], $publisher);
    app(ContentWorkflow::class)->apply($release, 'en', Transition::Publish, $publisher);

    $settings = app(PressKitSettings::class);
    $settings->boilerplate = ['am' => 'የናሙና መግለጫ', 'en' => 'Sample boilerplate'];
    $settings->contact_name = ['en' => 'Sample communication office'];
    $settings->contact_email = 'press@example.org';
    $settings->logo_ids = [$logo->id];
    $settings->form_id = $press->id;
    $settings->save();

    $this->get('/en/press')->assertOk()
        ->assertSee('Sample boilerplate')
        ->assertSee('Sample communication office')->assertSee('press@example.org')
        ->assertSee(route('media.file', ['uuid' => $logo->uuid, 'name' => $logo->file_name]), escape: false)
        ->assertSee('#3151ED')
        ->assertSee('Sample Chairperson')
        ->assertSee('Sample press release')
        ->assertSee(route('forms.show', ['locale' => 'en', 'slug' => 'sample-press-enquiry']), escape: false);

    // Afaan Oromo readers get English, Harari readers Amharic.
    $this->get('/om/press')->assertSee('Sample boilerplate');
    $this->get('/har/press')->assertSee('የናሙና መግለጫ');
});

it('lets communications staff fill in the press kit and keeps others out', function () {
    $this->actingAs(staff(SystemRole::Author))->get('/admin/press-kit')->assertForbidden();

    $this->actingAs(staff(SystemRole::CommunicationsOfficer));
    livewire(ManagePressKit::class)
        ->fillForm(['boilerplate' => ['om' => 'Ibsa fakkeenyaa', 'en' => ''], 'contact_phone' => '+251 25 666 0000'])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(PressKitSettings::class)->refresh();
    expect($settings->boilerplate)->toBe(['om' => 'Ibsa fakkeenyaa'])
        ->and($settings->contact_phone)->toBe('+251 25 666 0000');
});

it('shows ways to get involved, with what is coming later marked as such', function () {
    app(SaveEvent::class)->handle(new Event, [
        'starts_at' => CarbonImmutable::now()->addDays(4)->toDateTimeString(),
        'translations' => ['en' => ['title' => 'Sample community meeting', 'published' => true]],
    ], staff(SystemRole::Publisher));
    app(SaveForm::class)->handle(new Form, ['type' => 'feedback', 'translations' => ['en' => ['title' => 'Sample suggestions', 'published' => true]]], staff(SystemRole::Publisher));

    $this->get('/en/get-involved')->assertOk()
        ->assertSee('Sample community meeting')
        ->assertSee('Sample suggestions')
        ->assertSee(route('documents.notices', ['locale' => 'en']), escape: false)
        ->assertDontSee(__('involved.coming_soon', [], 'en'))
        ->assertSee(route('newsletter.create', ['locale' => 'en']), escape: false)
        ->assertSee(__('involved.membership.title', [], 'en'));

    $this->get('/sitemaps/en.xml')
        ->assertSee(url('/en/press'), escape: false)
        ->assertSee(url('/en/get-involved'), escape: false);
});
