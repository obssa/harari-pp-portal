<?php

namespace App\Filament\Support;

use App\Domain\Content\Contracts\TranslatableModel;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Organisation\Models\Leader;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;

/**
 * Form pieces shared by the office, leader and league screens (ADR-0017).
 */
final class DirectoryFields
{
    public static function body(string $locale, string $label): RichEditor
    {
        return RichEditor::make("translations.{$locale}.body")
            ->label($label)
            ->json()
            ->customBlocks(BodyRenderer::BLOCKS)
            ->toolbarButtons([['bold', 'italic', 'link'], ['h2', 'h3'], ['blockquote', 'bulletList', 'orderedList'], ['undo', 'redo']])
            ->extraInputAttributes(['lang' => $locale]);
    }

    public static function published(string $locale, string $permission): Toggle
    {
        return Toggle::make("translations.{$locale}.published")
            ->label(__('admin.pages.field.published'))
            ->disabled(fn (): bool => ! CurrentUser::get()->can($permission));
    }

    /**
     * @return list<TextInput>
     */
    public static function socials(): array
    {
        return array_map(
            fn (string $network): TextInput => TextInput::make("socials.{$network}")
                ->label(__("organisation.social.{$network}"))
                ->url()
                ->startsWith(['https://'])
                ->maxLength(300),
            Leader::SOCIALS,
        );
    }

    /**
     * The form state of every language of a record.
     *
     * @param  list<string>  $fields
     * @return array<string, array<string, mixed>>
     */
    public static function fill(Model&TranslatableModel $record, array $fields): array
    {
        $state = [];
        foreach ($record->translations()->get() as $translation) {
            $state[$translation->locale] = [
                ...$translation->only(['name', 'slug', 'body', ...$fields]),
                'published' => $translation->getAttribute('status') === ContentStatus::Published,
            ];
        }

        return $state;
    }

    public static function languagesColumn(): TextColumn
    {
        return TextColumn::make('languages')
            ->label(__('admin.posts.field.languages'))
            ->state(fn (Model&TranslatableModel $record): array => ($record->relationLoaded('translations') ? $record->getRelation('translations') : $record->translations()->get())
                ->map(fn (Model $t): string => strtoupper((string) $t->getAttribute('locale')).' · '.self::statusLabel($t->getAttribute('status')))
                ->values()->all())
            ->badge();
    }

    private static function statusLabel(mixed $status): string
    {
        return $status instanceof ContentStatus ? $status->label() : ContentStatus::Draft->label();
    }
}
