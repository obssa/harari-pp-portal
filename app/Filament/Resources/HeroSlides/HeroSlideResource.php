<?php

namespace App\Filament\Resources\HeroSlides;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Homepage\Actions\SaveHeroSlide;
use App\Domain\Homepage\Enums\SlideLayout;
use App\Domain\Homepage\Models\HeroSlide;
use App\Domain\Homepage\Support\SlideLink;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Organisation\Models\Leader;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\HeroSlides\Pages\CreateHeroSlide;
use App\Filament\Resources\HeroSlides\Pages\EditHeroSlide;
use App\Filament\Resources\HeroSlides\Pages\ListHeroSlides;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\MediaPicker;
use App\Settings\CarouselSettings;
use App\Support\HararPhotos;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use UnitEnum;

/**
 * The homepage carousel manager (ADR-0070): slides in drag-and-drop order,
 * each with its layout, picture, text in every language, buttons and
 * dates; the carousel's own settings sit on the list.
 */
class HeroSlideResource extends Resource
{
    protected static ?string $model = HeroSlide::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $slug = 'homepage-carousel';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('admin.carousel.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.carousel.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.carousel.nav');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.content');
    }

    public static function form(Schema $schema): Schema
    {
        $layoutIs = fn (SlideLayout ...$layouts): \Closure => fn (Get $get): bool => in_array(SlideLayout::tryFrom((string) $get('layout')), $layouts, true);

        return $schema->columns(3)->components([
            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 2])->schema([
                Section::make(__('admin.carousel.section.design'))
                    ->description(__('admin.carousel.section.design_hint'))
                    ->schema([
                        Radio::make('layout')
                            ->label(__('admin.carousel.field.layout'))
                            ->options(collect(SlideLayout::cases())->mapWithKeys(fn (SlideLayout $l): array => [$l->value => $l->label()])->all())
                            ->descriptions(collect(SlideLayout::cases())->mapWithKeys(fn (SlideLayout $l): array => [$l->value => $l->description()])->all())
                            ->default(SlideLayout::Photo->value)
                            ->required()
                            ->live(),
                        MediaPicker::make('image_id', [MediaKind::Image])
                            ->label(__('admin.carousel.field.image'))
                            ->helperText(fn (Get $get): string => match (SlideLayout::tryFrom((string) $get('layout')) ?? SlideLayout::Photo) {
                                SlideLayout::Photo => __('admin.carousel.help.image_photo'),
                                SlideLayout::Banner => __('admin.carousel.help.image_banner'),
                                SlideLayout::Portrait => __('admin.carousel.help.image_portrait'),
                            })
                            ->required(fn (Get $get): bool => (SlideLayout::tryFrom((string) $get('layout')) ?? SlideLayout::Photo)->needsImage()),
                        Grid::make(2)->schema([
                            TextInput::make('overlay')
                                ->label(__('admin.carousel.field.overlay'))
                                ->helperText(__('admin.carousel.help.overlay'))
                                ->numeric()->integer()->minValue(0)->maxValue(95)->suffix('%')
                                ->default(80),
                            Radio::make('align')
                                ->label(__('admin.carousel.field.align'))
                                ->options(['start' => __('admin.carousel.align.start'), 'center' => __('admin.carousel.align.center')])
                                ->default('start')
                                ->inline(),
                        ])->visible($layoutIs(SlideLayout::Photo)),
                    ]),

                Section::make(__('admin.carousel.section.text'))
                    ->description(__('admin.carousel.section.text_hint'))
                    ->schema([
                        Tabs::make('content')->tabs(array_map(fn (string $locale): Tab => Tab::make(Locales::nativeName($locale))
                            ->key('slide-lang-'.$locale)
                            ->extraAttributes(['lang' => $locale])
                            ->schema([
                                TextInput::make("content.{$locale}.kicker")->label(__('admin.carousel.field.kicker'))->maxLength(SaveHeroSlide::LIMITS['kicker']),
                                Textarea::make("content.{$locale}.title")->label(__('admin.carousel.field.title'))->rows(2)->maxLength(SaveHeroSlide::LIMITS['title']),
                                TextInput::make("content.{$locale}.highlight")->label(__('admin.carousel.field.highlight'))
                                    ->helperText(__('admin.carousel.help.highlight'))->maxLength(SaveHeroSlide::LIMITS['highlight']),
                                Textarea::make("content.{$locale}.text")->label(__('admin.carousel.field.text'))->rows(3)->maxLength(SaveHeroSlide::LIMITS['text']),
                                Textarea::make("content.{$locale}.note")->label(__('admin.carousel.field.note'))
                                    ->helperText(__('admin.carousel.help.note'))->rows(2)->maxLength(SaveHeroSlide::LIMITS['note'])
                                    ->visible($layoutIs(SlideLayout::Banner, SlideLayout::Portrait)),
                            ]), Locales::all())),
                    ]),

                Section::make(__('admin.carousel.section.buttons'))
                    ->description(__('admin.carousel.section.buttons_hint'))
                    ->schema([
                        Repeater::make('buttons')
                            ->hiddenLabel()
                            ->maxItems(HeroSlide::MAX_BUTTONS)
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->addActionLabel(__('admin.carousel.add_button'))
                            ->itemLabel(fn (array $state): string => trim((string) ($state['label'][Locales::default()] ?? '')) ?: __('admin.carousel.button'))
                            ->schema([
                                Grid::make(2)->schema([
                                    Select::make('kind')
                                        ->label(__('admin.carousel.field.link_kind'))
                                        ->options(collect(SlideLink::KINDS)->mapWithKeys(fn (string $k): array => [$k => __("admin.carousel.link.{$k}")])->all())
                                        ->default('route')->required()->native(false)->live(),
                                    Select::make('style')
                                        ->label(__('admin.carousel.field.style'))
                                        ->options(['primary' => __('admin.carousel.style.primary'), 'secondary' => __('admin.carousel.style.secondary')])
                                        ->default('primary')->required()->native(false),
                                ]),
                                Select::make('route')->label(__('admin.carousel.link.route'))
                                    ->options(fn (): array => SlideLink::routeOptions())->searchable()
                                    ->visible(fn (Get $get): bool => $get('kind') === 'route')->required(fn (Get $get): bool => $get('kind') === 'route'),
                                Select::make('leader')->label(__('admin.carousel.link.leader'))
                                    ->options(fn (): array => Leader::query()->with('translations')->orderBy('rank')->get()
                                        ->mapWithKeys(fn (Leader $l): array => [$l->id => $l->name()])->all())->searchable()
                                    ->visible(fn (Get $get): bool => $get('kind') === 'leader')->required(fn (Get $get): bool => $get('kind') === 'leader'),
                                Select::make('page')->label(__('admin.carousel.link.page'))
                                    ->options(fn (): array => Page::query()->with('translations')->get()
                                        ->mapWithKeys(fn (Page $p): array => [$p->id => (string) ($p->translation()?->getAttribute('title') ?? $p->id)])->all())->searchable()
                                    ->visible(fn (Get $get): bool => $get('kind') === 'page')->required(fn (Get $get): bool => $get('kind') === 'page'),
                                Select::make('post')->label(__('admin.carousel.link.post'))
                                    ->options(fn (): array => Post::query()->with('translations')->latest()->limit(200)->get()
                                        ->mapWithKeys(fn (Post $p): array => [$p->id => (string) ($p->translation()?->getAttribute('title') ?? $p->id)])->all())->searchable()
                                    ->visible(fn (Get $get): bool => $get('kind') === 'post')->required(fn (Get $get): bool => $get('kind') === 'post'),
                                TextInput::make('url')->label(__('admin.carousel.link.url'))->helperText(__('admin.carousel.help.url'))->maxLength(500)
                                    ->visible(fn (Get $get): bool => $get('kind') === 'url')->required(fn (Get $get): bool => $get('kind') === 'url'),
                                Fieldset::make(__('admin.carousel.field.label'))->columns(2)->schema(array_map(
                                    fn (string $locale): TextInput => TextInput::make("label.{$locale}")
                                        ->label(Locales::nativeName($locale))
                                        ->extraInputAttributes(['lang' => $locale])
                                        ->maxLength(SaveHeroSlide::LABEL_LIMIT),
                                    Locales::all(),
                                )),
                            ]),
                    ]),
            ]),

            Grid::make(1)->columnSpan(['default' => 3, 'lg' => 1])->schema([
                Section::make(__('admin.carousel.section.when'))->schema([
                    TextInput::make('name')->label(__('admin.carousel.field.name'))->helperText(__('admin.carousel.help.name'))->maxLength(120),
                    Toggle::make('active')->label(__('admin.carousel.field.active'))->default(true),
                    DateTimePicker::make('starts_at')->label(__('admin.carousel.field.starts_at'))->seconds(false),
                    DateTimePicker::make('ends_at')->label(__('admin.carousel.field.ends_at'))->seconds(false)->after('starts_at'),
                    TextInput::make('duration')->label(__('admin.carousel.field.duration'))
                        ->helperText(fn (): string => __('admin.carousel.help.duration', ['seconds' => CarouselSettings::current()['interval']]))
                        ->numeric()->integer()->minValue(3)->maxValue(30)->suffix(__('admin.carousel.seconds')),
                    CheckboxList::make('locales')->label(__('admin.carousel.field.locales'))->helperText(__('admin.carousel.help.locales'))
                        ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all())
                        ->columns(2),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort')
            ->defaultSort('sort')
            ->paginated(false)
            ->description(__('admin.carousel.list_hint'))
            ->emptyStateHeading(__('admin.carousel.empty'))
            ->emptyStateDescription(__('admin.carousel.empty_hint'))
            ->modifyQueryUsing(fn (Builder $query) => $query->with('image'))
            ->columns([
                ImageColumn::make('preview')
                    ->label(__('admin.carousel.field.image'))
                    ->state(fn (HeroSlide $record): ?string => $record->image?->adminPreviewUrl() ?? ($record->layout === SlideLayout::Photo ? url(HararPhotos::fallback('gate')) : null))
                    ->imageHeight(48)->imageWidth(80)->extraImgAttributes(['class' => 'rounded-md object-cover', 'alt' => '']),
                TextColumn::make('title')
                    ->label(__('admin.carousel.column.slide'))
                    ->state(fn (HeroSlide $record): string => $record->adminTitle())
                    ->description(fn (HeroSlide $record): string => $record->name)
                    ->wrap()->lineClamp(2),
                TextColumn::make('layout')->label(__('admin.carousel.field.layout'))
                    ->formatStateUsing(fn (SlideLayout $state): string => $state->label())->badge()->color('gray'),
                TextColumn::make('status')->label(__('admin.carousel.column.status'))
                    ->state(fn (HeroSlide $record): string => $record->status()->label())
                    ->color(fn (HeroSlide $record): string => $record->status()->color())
                    ->badge(),
                TextColumn::make('languages')->label(__('admin.carousel.column.languages'))
                    ->state(fn (HeroSlide $record): array => array_map('strtoupper', $record->languages()))
                    ->badge()->color('info'),
                TextColumn::make('schedule')->label(__('admin.carousel.column.schedule'))
                    ->state(fn (HeroSlide $record): string => self::schedule($record))
                    ->color('gray')->size('sm'),
                ToggleColumn::make('active')->label(__('admin.carousel.column.active')),
            ])
            ->filters([
                SelectFilter::make('layout')->label(__('admin.carousel.field.layout'))
                    ->options(collect(SlideLayout::cases())->mapWithKeys(fn (SlideLayout $l): array => [$l->value => $l->label()])->all()),
            ])
            ->recordActions([
                self::previewAction(),
                EditAction::make(),
                ActionGroup::make([
                    Action::make('duplicate')
                        ->label(__('admin.carousel.duplicate'))
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->color('gray')
                        ->authorize(fn (): bool => CurrentUser::get()->can('create', HeroSlide::class))
                        ->action(function (HeroSlide $record): void {
                            $copy = app(SaveHeroSlide::class)->handle(new HeroSlide, [
                                ...$record->only(['layout', 'image_id', 'overlay', 'align', 'content', 'buttons', 'locales', 'starts_at', 'ends_at', 'duration']),
                                'layout' => $record->layout->value,
                                'name' => __('admin.carousel.copy_name', ['name' => $record->name]),
                                'active' => false,
                            ], CurrentUser::get());
                            Notification::make()->success()->title(__('admin.carousel.duplicated'))->send();
                            redirect(self::getUrl('edit', ['record' => $copy]));
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('show')->label(__('admin.carousel.bulk_show'))->icon(Heroicon::OutlinedEye)
                        ->action(fn (Collection $records) => $records->each(fn (Model $slide) => $slide->forceFill(['active' => true, 'updated_by' => CurrentUser::get()->id])->save()))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('hide')->label(__('admin.carousel.bulk_hide'))->icon(Heroicon::OutlinedEyeSlash)
                        ->action(fn (Collection $records) => $records->each(fn (Model $slide) => $slide->forceFill(['active' => false, 'updated_by' => CurrentUser::get()->id])->save()))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Opens the slide on its own, as visitors would see it, through a link
     * that works for an hour (also for slides that are off or scheduled).
     */
    public static function previewAction(): Action
    {
        return Action::make('preview')
            ->label(__('admin.carousel.preview'))
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->url(fn (HeroSlide $record): string => URL::temporarySignedRoute('preview.slide', now()->addHour(), [
                'slide' => $record->id,
                'language' => Locales::isSupported(app()->getLocale()) ? app()->getLocale() : Locales::default(),
            ]), shouldOpenInNewTab: true);
    }

    private static function schedule(HeroSlide $record): string
    {
        $format = fn (\DateTimeInterface $date): string => $date->format('j M Y, H:i');

        return match (true) {
            $record->starts_at !== null && $record->ends_at !== null => __('admin.carousel.schedule.between', ['from' => $format($record->starts_at), 'until' => $format($record->ends_at)]),
            $record->starts_at !== null => __('admin.carousel.schedule.from', ['date' => $format($record->starts_at)]),
            $record->ends_at !== null => __('admin.carousel.schedule.until', ['date' => $format($record->ends_at)]),
            default => __('admin.carousel.schedule.always'),
        };
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHeroSlides::route('/'),
            'create' => CreateHeroSlide::route('/create'),
            'edit' => EditHeroSlide::route('/{record}/edit'),
        ];
    }
}
