<?php

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Domain\Translation\Actions\EditUiTranslation;
use App\Domain\Translation\Models\UiTranslation;
use App\Domain\Translation\Models\UiTranslationRevision;
use App\Domain\Translation\Support\TranslationRows;
use App\Filament\Pages\ManageTranslations;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Livewire\livewire;

function hararTranslator(): User
{
    $user = staff(SystemRole::Translator);
    $user->assignScopedRole(SystemRole::Translator, ScopeType::Language, 'har');

    return $user->refresh();
}

it('saves a translation with the English it came from, and shows it on the site', function () {
    $translator = hararTranslator();

    app(EditUiTranslation::class)->handle('nav.home', 'har', 'Sample Harari home', $translator);

    expect(UiTranslation::query()->where('key', 'nav.home')->firstOrFail()->text)->toBe(['har' => 'Sample Harari home']);
    $revision = UiTranslationRevision::query()->firstOrFail();
    expect($revision->source_text)->toBe(__('nav.home', [], 'en'))
        ->and($revision->new_text)->toBe('Sample Harari home')
        ->and($revision->user_id)->toBe($translator->id);
    expect(__('nav.home', [], 'har'))->toBe('Sample Harari home');

    // Clearing it removes the admin's version.
    app(EditUiTranslation::class)->handle('nav.home', 'har', '', $translator);
    expect(UiTranslation::query()->where('key', 'nav.home')->exists())->toBeFalse()
        ->and(UiTranslationRevision::query()->count())->toBe(2);
});

it('refuses other languages, unknown keys and changed placeholders', function () {
    $translator = hararTranslator();

    expect(fn () => app(EditUiTranslation::class)->handle('nav.home', 'am', 'x', $translator))->toThrow(HttpException::class)
        ->and(fn () => app(EditUiTranslation::class)->handle('no.such.key', 'har', 'x', $translator))->toThrow(ValidationException::class)
        ->and(fn () => app(EditUiTranslation::class)->handle('events.registration.closes', 'har', 'Without the date', $translator))->toThrow(ValidationException::class);

    app(EditUiTranslation::class)->handle('events.registration.closes', 'har', 'Sample :date', $translator);
    expect(UiTranslationRevision::query()->count())->toBe(1);
});

it('marks strings that are missing or whose English changed after translating', function () {
    $translator = hararTranslator();
    $admin = staff(SystemRole::SuperAdmin);
    app(EditUiTranslation::class)->handle('nav.home', 'har', 'Sample Harari home', $translator);

    $rows = app(TranslationRows::class)->for('har');
    expect($rows['nav.home']['status'])->toBe(TranslationRows::TRANSLATED)
        ->and($rows['nav.news']['status'])->toBe(TranslationRows::MISSING);

    app(EditUiTranslation::class)->handle('nav.home', 'en', 'Sample new English home', $admin);
    expect(app(TranslationRows::class)->for('har')['nav.home']['status'])->toBe(TranslationRows::CHANGED)
        ->and(app(TranslationRows::class)->for('am')['nav.home']['status'])->toBe(TranslationRows::TRANSLATED);
});

it('shows the grid one language at a time and lets translators fix their language', function () {
    $this->actingAs(staff(SystemRole::Author))->get('/admin/translations')->assertForbidden();

    $translator = hararTranslator();
    $this->actingAs($translator);
    $this->get('/admin/translations')->assertOk();

    livewire(ManageTranslations::class)
        ->assertSet('language', 'har')
        ->filterTable('status', TranslationRows::MISSING)
        ->searchTable('nav.home')
        ->assertSee('nav.home')
        ->callAction(TestAction::make('edit')->table('nav.home'), data: ['text' => 'Sample Harari from the grid'])
        ->assertHasNoActionErrors();

    expect(__('nav.home', [], 'har'))->toBe('Sample Harari from the grid');
});
