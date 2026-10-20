<?php

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Domain\Content\Actions\SavePost;
use App\Domain\Content\Models\Post;
use App\Domain\Translation\Jobs\TranslateMissingStrings;
use App\Domain\Translation\Models\GlossaryTerm;
use App\Domain\Translation\Models\UiTranslationRevision;
use App\Domain\Translation\Support\TranslationRows;
use App\Filament\Pages\ManageTranslations;
use App\Models\User;
use App\Services\Translation\MachineTranslator;
use App\Settings\IntegrationSettings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Livewire\livewire;

function useMachine(string $driver = 'google'): void
{
    $settings = app(IntegrationSettings::class);
    $settings->mt_driver = $driver;
    $settings->google_translate_api_key = 'sample-google-key';
    $settings->llm_api_key = 'sample-llm-key';
    $settings->llm_provider = 'anthropic';
    $settings->save();
}

/** Google answers with "OM " before each text it received. */
function fakeGoogle(?Closure $answer = null): void
{
    Http::fake(['translation.googleapis.com/*' => fn (Request $request) => Http::response(['data' => ['translations' => array_map(
        fn (string $q): array => ['translatedText' => $answer ? $answer($q) : 'OM '.$q],
        $request['q'],
    )]])]);
}

function omTranslator(): User
{
    $user = staff(SystemRole::Translator);
    $user->assignScopedRole(SystemRole::Translator, ScopeType::Language, 'om');

    return $user->refresh();
}

it('stays off until a driver and key are saved, and never translates into Harari', function () {
    expect(app(MachineTranslator::class)->enabledFor('om'))->toBeFalse();

    useMachine();
    $translator = app(MachineTranslator::class);
    expect($translator->enabledFor('om'))->toBeTrue()->and($translator->enabledFor('am'))->toBeTrue()
        ->and($translator->enabledFor('har'))->toBeFalse()
        ->and(fn () => $translator->translate(['Home'], 'en', 'har'))->toThrow(RuntimeException::class);
});

it('hides placeholders from the service and drops answers that lose them', function () {
    useMachine();
    $drop = false;
    fakeGoogle(function (string $q) use (&$drop): string {
        return $drop ? str_replace('⟦0⟧', '', $q) : 'OM '.$q;
    });

    expect(app(MachineTranslator::class)->translate(['a' => 'Closes :date', 'b' => '', 'c' => 'Home'], 'en', 'om'))
        ->toBe(['b' => '', 'a' => 'OM Closes :date', 'c' => 'OM Home']);
    Http::assertSent(fn (Request $request): bool => $request['q'] === ['Closes ⟦0⟧', 'Home'] && $request['target'] === 'om');

    $drop = true;
    expect(app(MachineTranslator::class)->translate(['Closes :date'], 'en', 'om'))->toBe([null]);
});

it('asks an AI model with the approved terms and reads its list', function () {
    useMachine('llm');
    GlossaryTerm::query()->create(['terms' => ['en' => 'Sample Party', 'om' => 'Paartii Fakkeenyaa']]);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => "```json\n[\"Fuula Paartii Fakkeenyaa\"]\n```"]]])]);

    expect(app(MachineTranslator::class)->translate(['Sample Party home'], 'en', 'om'))->toBe(['Fuula Paartii Fakkeenyaa']);
    Http::assertSent(fn (Request $request): bool => str_contains((string) $request['system'], 'Sample Party → Paartii Fakkeenyaa')
        && $request['model'] === 'claude-sonnet-5-5' && $request->header('x-api-key') === ['sample-llm-key']);
});

it('marks machine translations for review in the grid until a person checks them', function () {
    useMachine();
    fakeGoogle();
    $this->actingAs(omTranslator());

    livewire(ManageTranslations::class, ['language' => 'om'])
        ->searchTable('nav.home')
        ->callAction(TestAction::make('machine')->table('nav.home'))
        ->assertHasNoActionErrors();
    expect(__('nav.home', [], 'om'))->toBe('OM '.__('nav.home', [], 'en'))
        ->and(app(TranslationRows::class)->for('om')['nav.home']['status'])->toBe(TranslationRows::MACHINE)
        ->and(UiTranslationRevision::query()->latest('id')->value('origin'))->toBe('machine');

    livewire(ManageTranslations::class, ['language' => 'om'])
        ->searchTable('nav.home')
        ->callAction(TestAction::make('review')->table('nav.home'));
    expect(app(TranslationRows::class)->for('om')['nav.home']['status'])->toBe(TranslationRows::TRANSLATED);
});

it('translates every missing string in the background', function () {
    useMachine();
    fakeGoogle();
    // A small set of language files where Afaan Oromo misses two strings.
    $lang = storage_path('framework/testing/lang-'.uniqid());
    mkdir($lang, 0775, true);
    file_put_contents("{$lang}/en.json", json_encode(['nav.home' => 'Home', 'nav.news' => 'News', 'events.registration.closes' => 'Registration closes :date.']));
    file_put_contents("{$lang}/om.json", json_encode(['nav.home' => '', 'nav.news' => 'Oduu', 'events.registration.closes' => '']));
    app()->useLangPath($lang);

    TranslateMissingStrings::dispatchSync('om', omTranslator()->id);

    expect(UiTranslationRevision::query()->where('origin', 'machine')->pluck('new_text', 'key')->sortKeys()->all())
        ->toBe(['events.registration.closes' => 'OM Registration closes :date.', 'nav.home' => 'OM Home']);
});

it('translates a post body word by word and keeps the review mark until it is ticked', function () {
    useMachine();
    fakeGoogle();
    $body = ['type' => 'doc', 'content' => [
        ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Sample '], ['type' => 'text', 'text' => 'bold words', 'marks' => [['type' => 'bold']]]]],
        ['type' => 'customBlock', 'attrs' => ['id' => 'statistics', 'config' => []]],
    ]];

    $translated = app(MachineTranslator::class)->translateBody($body, 'en', 'om');
    expect($translated['content'][0]['content'][1])->toBe(['type' => 'text', 'text' => 'OM bold words', 'marks' => [['type' => 'bold']]])
        ->and($translated['content'][1])->toBe($body['content'][1]);

    $publisher = staff(SystemRole::Publisher);
    $post = app(SavePost::class)->handle(new Post, ['type' => 'news', 'translations' => [
        'en' => ['title' => 'Sample source'],
        'om' => ['title' => 'OM Sample source', 'body' => $translated, 'machine_translated' => true],
    ]], $publisher);
    expect($post->translation('om', fallback: false)?->getAttribute('machine_translated_at'))->not->toBeNull();

    app(SavePost::class)->handle($post, ['type' => 'news', 'translations' => ['om' => ['title' => 'Fakkeenya madda', 'machine_reviewed' => true]]], $publisher);
    expect($post->refresh()->translation('om', fallback: false)?->getAttribute('machine_translated_at'))->toBeNull();
});
