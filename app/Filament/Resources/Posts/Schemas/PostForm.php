<?php

namespace App\Filament\Resources\Posts\Schemas;

use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Enums\PostType;
use App\Domain\Content\Models\Category;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\Tag;
use App\Domain\Content\Support\BodyRenderer;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Organisation\Models\League;
use App\Domain\Organisation\Models\Office;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\Offices\OfficeResource;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\MediaPicker;
use App\Filament\Support\Translatable;
use App\Services\Calendar\EthiopianCalendar;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(['default' => 3, 'lg' => 2])
                    ->schema([
                        Translatable::tabs(fn (string $locale): array => self::languageFields($locale)),
                    ]),
                Section::make(__('admin.posts.section.details'))
                    ->columnSpan(['default' => 3, 'lg' => 1])
                    ->disabled(fn (?Post $record): bool => $record !== null && ! CurrentUser::get()->can('updateStructure', $record))
                    ->schema([
                        TextEntry::make('source_name')
                            ->label(__('import.post.source'))
                            ->url(fn (?Post $record): ?string => $record?->source_url, shouldOpenInNewTab: true)
                            ->helperText(__('import.post.source_help'))
                            ->visible(fn (?Post $record): bool => filled($record?->source_url)),
                        Select::make('type')
                            ->label(__('admin.posts.field.type'))
                            ->options(PostType::options())
                            ->default(PostType::News->value)
                            ->required(),
                        Select::make('source_locale')
                            ->label(__('admin.posts.field.source_locale'))
                            ->helperText(__('admin.posts.help.source_locale'))
                            ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all()),
                        MediaPicker::make('featured_image_id', [MediaKind::Image])
                            ->label(__('admin.posts.field.featured_image'))
                            ->helperText(__('admin.posts.help.featured_image')),
                        Select::make('categories')
                            ->label(__('admin.categories.plural'))
                            ->multiple()
                            ->options(fn (): array => Category::query()->where('type', Category::TYPE_POST)->with('translations')->orderBy('sort')->get()
                                ->mapWithKeys(fn (Category $c): array => [$c->id => $c->name()])->all()),
                        Select::make('tags')
                            ->label(__('admin.tags.plural'))
                            ->multiple()
                            ->searchable()
                            ->options(fn (): array => Tag::query()->with('translations')->latest()->limit(200)->get()
                                ->mapWithKeys(fn (Tag $t): array => [$t->id => $t->name()])->all()),
                        Select::make('offices')
                            ->label(__('admin.offices.plural'))
                            ->multiple()
                            ->searchable()
                            ->options(fn (): array => Office::query()->with('translations')->orderBy('sort')->get()
                                ->mapWithKeys(fn (Office $o): array => [$o->id => $o->name()])->all()),
                        Select::make('leagues')
                            ->label(__('admin.leagues.plural'))
                            ->multiple()
                            ->options(fn (): array => League::query()->with('translations')->orderBy('sort')->get()
                                ->mapWithKeys(fn (League $l): array => [$l->id => $l->name()])->all()),
                        Select::make('leaders')
                            ->label(__('admin.posts.field.leaders'))
                            ->helperText(__('admin.posts.help.leaders'))
                            ->multiple()
                            ->searchable()
                            ->options(fn (): array => OfficeResource::leaderOptions()),
                        Toggle::make('is_featured')
                            ->label(__('admin.posts.field.is_featured'))
                            ->visible(fn (): bool => CurrentUser::get()->can('posts.feature')),
                        Toggle::make('is_sticky')
                            ->label(__('admin.posts.field.is_sticky'))
                            ->visible(fn (): bool => CurrentUser::get()->can('posts.feature')),
                    ]),
            ]);
    }

    /**
     * @return array<Component|Field>
     */
    private static function languageFields(string $locale): array
    {
        $canPublish = fn (): bool => CurrentUser::get()->can('posts.publish');

        return [
            Translatable::mainField($locale, 'title', 200)
                ->label(__('admin.posts.field.title')),
            TextInput::make("translations.{$locale}.kicker")
                ->label(__('admin.posts.field.kicker'))
                ->helperText(__('admin.posts.help.kicker'))
                ->maxLength(120),
            Translatable::slugField($locale, 'post_translations'),
            Textarea::make("translations.{$locale}.excerpt")
                ->label(__('admin.posts.field.excerpt'))
                ->helperText(__('admin.posts.help.excerpt'))
                ->rows(3)
                ->maxLength(400),
            RichEditor::make("translations.{$locale}.body")
                ->label(__('admin.posts.field.body'))
                ->json()
                ->customBlocks(BodyRenderer::BLOCKS)
                ->toolbarButtons([
                    ['bold', 'italic', 'link'],
                    ['h2', 'h3'],
                    ['blockquote', 'bulletList', 'orderedList'],
                    ['table'],
                    ['undo', 'redo'],
                ])
                ->extraInputAttributes(['lang' => $locale]),
            Grid::make(2)->schema([
                TextEntry::make("workflow_status_{$locale}")
                    ->label(__('admin.posts.field.status'))
                    ->state(fn (?Post $record): string => ($record?->translation($locale, fallback: false)->status ?? ContentStatus::Draft)->label())
                    ->color(fn (?Post $record): string => ($record?->translation($locale, fallback: false)->status ?? ContentStatus::Draft)->color())
                    ->badge(),
                DateTimePicker::make("translations.{$locale}.published_at")
                    ->label(__('admin.posts.field.published_at'))
                    ->helperText(__('admin.posts.help.published_at'))
                    ->timezone(EthiopianCalendar::timezone())
                    ->seconds(false)
                    ->visible(fn (): bool => $canPublish()),
            ]),
            WorkflowActions::make($locale),
            Section::make(__('admin.posts.section.seo'))
                ->collapsed()
                ->compact()
                ->schema([
                    TextInput::make("translations.{$locale}.seo_title")
                        ->label(__('admin.posts.field.seo_title'))
                        ->maxLength(70),
                    Textarea::make("translations.{$locale}.seo_description")
                        ->label(__('admin.posts.field.seo_description'))
                        ->rows(2)
                        ->maxLength(170),
                    Toggle::make("translations.{$locale}.noindex")
                        ->label(__('admin.posts.field.noindex')),
                ]),
        ];
    }
}
