<?php

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Sites\Models\Site;
use App\Domain\Sites\Settings\SiteSettingsRepository;
use App\Filament\Pages\ManageIntegrations;
use App\Filament\Pages\ManageSiteSettings;
use App\Settings\IntegrationSettings;
use App\Settings\SiteSettings;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

use function Pest\Livewire\livewire;

it('ships sensible defaults', function () {
    $site = app(SiteSettings::class);
    $integrations = app(IntegrationSettings::class);

    expect($site->default_locale)->toBe('am')
        ->and($site->calendar_primary)->toBe('ethiopian')
        ->and($site->show_secondary_calendar)->toBeTrue()
        ->and($integrations->sms_driver)->toBe('log')
        ->and($integrations->isConfigured('llm_api_key'))->toBeFalse();
});

it('lets a Super Admin change site settings', function () {
    $this->actingAs(staff(SystemRole::SuperAdmin));

    livewire(ManageSiteSettings::class)
        ->fillForm([
            'default_locale' => 'om',
            'calendar_primary' => 'gregorian',
            'contact_email' => 'info@example.org',
            'hotline' => '6070',
            'address' => ['en' => 'Harar, Jugol', 'am' => 'ሐረር፣ ጁጎል', 'om' => ''],
            'social_links' => ['telegram' => 'https://t.me/example', 'facebook' => ''],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $settings = app(SiteSettings::class)->refresh();
    expect($settings->default_locale)->toBe('om')
        ->and($settings->calendar_primary)->toBe('gregorian')
        ->and($settings->address)->toEqual(['en' => 'Harar, Jugol', 'am' => 'ሐረር፣ ጁጎል'])
        ->and($settings->social_links)->toBe(['telegram' => 'https://t.me/example'])
        ->and($settings->localized('address', 'har'))->toBe('ሐረር፣ ጁጎል');
});

it('validates contact details and links', function () {
    $this->actingAs(staff(SystemRole::SuperAdmin));

    livewire(ManageSiteSettings::class)
        ->fillForm(['contact_email' => 'not-an-email', 'social_links' => ['youtube' => 'not a url']])
        ->call('save')
        ->assertHasFormErrors(['contact_email', 'social_links.youtube']);
});

it('lets an Admin read but not change site settings', function () {
    $this->actingAs(staff(SystemRole::Admin));

    $this->get('/admin/settings/site')->assertOk();

    livewire(ManageSiteSettings::class)
        ->fillForm(['default_locale' => 'en'])
        ->call('save');

    expect(app(SiteSettings::class)->refresh()->default_locale)->toBe('am');
});

it('keeps settings away from other staff', function (SystemRole $role) {
    $this->actingAs(staff($role))->get('/admin/settings/site')->assertForbidden();
})->with([SystemRole::Publisher, SystemRole::Editor, SystemRole::Analyst]);

it('keeps API keys for Super Admins only', function (SystemRole $role, int $status) {
    $this->actingAs(staff($role))->get('/admin/settings/integrations')->assertStatus($status);
})->with([
    [SystemRole::SuperAdmin, 200],
    [SystemRole::Admin, 403],
    [SystemRole::Publisher, 403],
]);

it('encrypts API keys at rest and never shows them again', function () {
    $this->actingAs(staff(SystemRole::SuperAdmin));

    livewire(ManageIntegrations::class)
        ->fillForm(['llm_api_key' => 'sk-test-secret-123', 'telegram_bot_token' => 'bot-secret-456'])
        ->call('save')
        ->assertHasNoFormErrors();

    $raw = DB::table('settings')->where('group', 'integrations')->where('name', 'llm_api_key')->value('payload');
    expect($raw)->not->toContain('sk-test-secret-123')
        ->and(app(IntegrationSettings::class)->refresh()->llm_api_key)->toBe('sk-test-secret-123');

    livewire(ManageIntegrations::class)
        ->assertFormSet(['llm_api_key' => null])
        ->assertDontSee('sk-test-secret-123');
});

it('keeps a saved key when its field is left empty, and removes it on request', function () {
    $this->actingAs(staff(SystemRole::SuperAdmin));
    $settings = app(IntegrationSettings::class);
    $settings->youtube_api_key = 'yt-key';
    $settings->save();

    livewire(ManageIntegrations::class)
        ->fillForm(['sms_sender_id' => 'HarariPP'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect(app(IntegrationSettings::class)->refresh()->youtube_api_key)->toBe('yt-key')
        ->and(app(IntegrationSettings::class)->sms_sender_id)->toBe('HarariPP');

    livewire(ManageIntegrations::class)
        ->fillForm(['remove_secrets' => ['youtube_api_key']])
        ->call('save');
    expect(app(IntegrationSettings::class)->refresh()->youtube_api_key)->toBeNull();
});

it('records settings changes without writing secrets to the audit log', function () {
    $this->actingAs($root = staff(SystemRole::SuperAdmin));

    livewire(ManageIntegrations::class)
        ->fillForm(['llm_api_key' => 'sk-never-log-me', 'sms_driver' => 'twilio'])
        ->call('save');

    $entry = Activity::query()->where('log_name', 'settings')->latest('id')->firstOrFail();
    expect(json_encode($entry->properties))->not->toContain('sk-never-log-me')
        ->and($entry->properties['attributes']['sms_driver'])->toBe('twilio')
        ->and($entry->properties['attributes']['llm_api_key'])->toBe('•••• (changed)')
        ->and($entry->causer?->is($root))->toBeTrue();
});

it('uses the default language from the admin for "/"', function () {
    $settings = app(SiteSettings::class);
    $settings->default_locale = 'om';
    $settings->save();

    $this->withHeader('Accept-Language', '')->get('/')->assertRedirect('/om');
    $this->get('/en')->assertSee('<link rel="alternate" hreflang="x-default" href="'.url('/om').'">', escape: false);
});

it('starts with one default site', function () {
    Site::forgetCurrent();

    expect(Site::query()->count())->toBe(1)
        ->and(Site::current()->key)->toBe('harari')
        ->and(Site::current()->is_default)->toBeTrue();
});

it('serves a site whose domain matches the request', function () {
    Site::forgetCurrent();
    Site::query()->create(['key' => 'youth', 'name' => 'Youth League', 'domain' => 'localhost']);

    expect(Site::current()->key)->toBe('youth');
    Site::forgetCurrent();
});

it('stores every setting against the default site', function () {
    $default = Site::query()->where('is_default', true)->value('id');

    expect(DB::table('settings')->distinct()->pluck('site_id')->all())->toBe([$default]);
});

it('keeps each site’s settings apart', function () {
    $other = Site::query()->create(['key' => 'youth', 'name' => 'Youth League (sample)', 'domain' => 'youth.example.org']);
    expect(config('settings.repositories.database.type'))->toBe(SiteSettingsRepository::class);
    $repository = new SiteSettingsRepository(config('settings.repositories.database'));

    Site::setCurrent($other);
    $repository->createProperty('site', 'default_locale', 'en');
    $repository->updatePropertiesPayload('site', ['default_locale' => 'om']);
    expect($repository->getPropertiesInGroup('site'))->toBe(['default_locale' => 'om'])
        ->and($repository->checkIfPropertyExists('site', 'calendar_primary'))->toBeFalse();

    Site::forgetCurrent();
    expect($repository->getPropertyPayload('site', 'default_locale'))->toBe('am')
        ->and(app(SiteSettings::class)->refresh()->default_locale)->toBe('am')
        ->and(DB::table('settings')->where('site_id', $other->id)->count())->toBe(1);
});
