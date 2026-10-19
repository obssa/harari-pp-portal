<?php

namespace App\Filament\Resources\Media\Schemas;

use App\Domain\Media\Enums\MediaKind;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaFolder;
use App\Domain\Media\Models\MediaUsage;
use App\Domain\Translation\Support\Locales;
use App\Filament\Support\CalendarDate;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class MediaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(2)
                    ->schema([
                        Section::make(__('admin.media.section.details'))
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label(__('admin.media.field.name'))
                                    ->required()
                                    ->maxLength(120),
                                Select::make('folder_id')
                                    ->label(__('admin.media.field.folder'))
                                    ->options(fn (): array => MediaFolder::options())
                                    ->placeholder(__('admin.media.folder.none')),
                                TextInput::make('credit')
                                    ->label(__('admin.media.field.credit'))
                                    ->helperText(__('admin.media.help.credit'))
                                    ->maxLength(255),
                                TagsInput::make('tags')
                                    ->label(__('admin.media.field.tags')),
                            ]),
                        Section::make(__('admin.media.section.alt'))
                            ->description(__('admin.media.help.alt'))
                            ->visible(fn (?Media $record): bool => (bool) $record?->isImage())
                            ->columns(2)
                            ->schema(self::altFields()),
                        Section::make(__('admin.media.section.focal'))
                            ->description(__('admin.media.help.focal'))
                            ->visible(fn (?Media $record): bool => $record?->kind === MediaKind::Image)
                            ->columns(2)
                            ->schema([
                                ViewField::make('focal_picker')
                                    ->view('filament.media.focal-point')
                                    ->hiddenLabel()
                                    ->dehydrated(false)
                                    ->columnSpanFull(),
                                TextInput::make('focal_x')
                                    ->label(__('admin.media.field.focal_x'))
                                    ->numeric()->integer()->minValue(0)->maxValue(100)->suffix('%')
                                    ->required()
                                    ->live(onBlur: true),
                                TextInput::make('focal_y')
                                    ->label(__('admin.media.field.focal_y'))
                                    ->numeric()->integer()->minValue(0)->maxValue(100)->suffix('%')
                                    ->required()
                                    ->live(onBlur: true),
                            ]),
                    ]),
                Section::make(__('admin.media.section.file'))
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('kind')
                            ->label(__('admin.media.field.kind'))
                            ->formatStateUsing(fn (MediaKind $state): string => $state->label()),
                        TextEntry::make('mime_type')
                            ->label(__('admin.media.field.mime')),
                        TextEntry::make('size')
                            ->label(__('admin.media.field.size'))
                            ->formatStateUsing(fn (int $state): string => Number::fileSize($state, precision: 1)),
                        TextEntry::make('dimensions')
                            ->label(__('admin.media.field.dimensions'))
                            ->state(fn (Media $record): ?string => $record->width ? "{$record->width} × {$record->height}" : null)
                            ->placeholder('—'),
                        TextEntry::make('uploader.name')
                            ->label(__('admin.media.field.uploaded_by'))
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label(__('admin.media.field.uploaded_at'))
                            ->formatStateUsing(CalendarDate::format(withTime: true)),
                        TextEntry::make('url')
                            ->label(__('admin.media.field.url'))
                            ->state(fn (Media $record): string => $record->getUrl())
                            ->url(fn (Media $record): string => $record->getUrl(), shouldOpenInNewTab: true)
                            ->copyable()
                            ->limit(48),
                        TextEntry::make('usages')
                            ->label(__('admin.media.field.used_in'))
                            ->state(fn (Media $record): array => $record->usages()->get()
                                ->map(fn (MediaUsage $usage): string => $usage->label())->all())
                            ->listWithLineBreaks()
                            ->placeholder(__('admin.media.usage.none')),
                    ]),
            ]);
    }

    /**
     * One field per language; Harari is optional and falls back to Amharic.
     *
     * @return list<Textarea>
     */
    private static function altFields(): array
    {
        $names = (array) config('portal.locale_names');

        return array_map(
            fn (string $locale): Textarea => Textarea::make("alt.{$locale}")
                ->label(__('admin.media.field.alt_in', ['language' => $names[$locale] ?? $locale]))
                ->rows(2)
                ->maxLength(250)
                ->extraInputAttributes(['lang' => $locale, 'dir' => 'ltr']),
            Locales::all(),
        );
    }
}
