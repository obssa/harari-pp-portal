<?php

namespace App\Filament\Support;

use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Content\Models\Translation;
use App\Domain\Content\Support\Slugger;
use App\Domain\Translation\Support\Locales;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Language tabs for translatable records (PLAN.md §6: EN · አማ · OM · HAR on
 * every record). Fields live under `translations.{locale}.{field}`; a
 * language whose main field is empty is not saved (or is removed).
 */
final class Translatable
{
    /**
     * @param  Closure(string $locale): array<Component|Field>  $fields
     */
    public static function tabs(Closure $fields): Tabs
    {
        return Tabs::make('translations')
            ->label(__('admin.translations.tabs'))
            ->columnSpanFull()
            ->tabs(array_map(
                fn (string $locale): Tab => Tab::make(Locales::nativeName($locale))
                    ->key('lang-'.$locale)
                    ->extraAttributes(['lang' => $locale])
                    ->schema($fields($locale)),
                Locales::all(),
            ));
    }

    /**
     * The main text field of one language (name, title). The first language's
     * field also checks that at least one language was filled in.
     */
    public static function mainField(string $locale, string $field, int $maxLength): TextInput
    {
        $input = TextInput::make("translations.{$locale}.{$field}")->maxLength($maxLength);

        if ($locale !== Locales::all()[0]) {
            return $input;
        }

        // Server-side only (no browser "required" prompt): another language may be the filled one.
        $others = array_map(
            fn (string $other): string => "translations.{$other}.{$field}",
            array_values(array_diff(Locales::all(), [$locale])),
        );

        return $input
            ->requiredWithoutAll($others)
            ->validationMessages(['required_without_all' => __('admin.translations.one_required')]);
    }

    /**
     * The slug field of one language: optional, Latin, unique in that language.
     */
    public static function slugField(string $locale, string $table, ?Closure $currentId = null): TextInput
    {
        return TextInput::make("translations.{$locale}.slug")
            ->label(__('admin.translations.slug'))
            ->helperText(__('admin.translations.slug_help'))
            ->maxLength(Slugger::MAX_LENGTH)
            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
            ->rules([
                fn (?Model $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($locale, $table, $record): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }

                    $foreignKey = $record instanceof TranslatableModel ? $record::translationForeignKey() : null;
                    $taken = DB::table($table)
                        ->where('locale', $locale)
                        ->where('slug', $value)
                        ->when($record && $foreignKey, fn ($query) => $query->where($foreignKey, '!=', $record?->getKey()))
                        ->exists();

                    if ($taken) {
                        $fail(__('admin.translations.slug_taken'));
                    }
                },
            ]);
    }

    /**
     * Form state for a record's translations.
     *
     * @param  list<string>  $fields
     * @return array<string, array<string, mixed>>
     */
    public static function fill(TranslatableModel&Model $record, array $fields): array
    {
        $state = [];
        /** @var iterable<Translation> $translations */
        $translations = $record->relationLoaded('translations')
            ? $record->getRelation('translations')
            : $record->translations()->get();
        foreach ($translations as $translation) {
            $state[$translation->locale] = $translation->only([...$fields, 'slug']);
        }

        return $state;
    }

    /**
     * Saves the languages whose main field is filled and removes the others.
     *
     * @param  array<string, array<string, mixed>>  $translations
     */
    public static function save(TranslatableModel&Model $record, array $translations, string $mainField): void
    {
        foreach (Locales::all() as $locale) {
            $values = $translations[$locale] ?? [];
            $main = trim((string) ($values[$mainField] ?? ''));

            if ($main === '') {
                $record->translations()->where('locale', $locale)->get()->each->delete();

                continue;
            }

            $values = array_map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value, $values);
            $values['slug'] = ($values['slug'] ?? '') === '' ? null : $values['slug'];
            $record->putTranslation($locale, $values);
        }

        $record->unsetRelation('translations');
    }

    /**
     * Refuses a record with no language at all.
     *
     * @param  array<string, array<string, mixed>>  $translations
     */
    public static function requireOne(array $translations, string $mainField): void
    {
        foreach ($translations as $values) {
            if (trim((string) ($values[$mainField] ?? '')) !== '') {
                return;
            }
        }

        throw ValidationException::withMessages([
            'data.translations' => __('admin.translations.one_required'),
        ]);
    }
}
