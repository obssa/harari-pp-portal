<?php

namespace App\Filament\Pages;

use App\Domain\Content\Models\Post;
use App\Domain\Homepage\Actions\SaveHomepage;
use App\Domain\Homepage\Enums\SectionKey;
use App\Domain\Homepage\Models\HomepageSection;
use App\Domain\Homepage\Support\HomepageLayout;
use App\Domain\Translation\Support\Locales;
use App\Filament\Resources\Offices\OfficeResource;
use App\Filament\Support\CurrentUser;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The homepage builder (PLAN.md §7): drag the sections into order, switch
 * them on or off, set limits, hand-pick posts, and give headings and
 * languages per section (ADR-0019).
 *
 * @property-read Schema $form
 */
class ManageHomepage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static ?string $slug = 'homepage';

    protected static ?int $navigationSort = 1;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('homepage.admin.title');
    }

    public function getTitle(): string
    {
        return __('homepage.admin.title');
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->can('homepage.manage');
    }

    public function mount(): void
    {
        $this->form->fill(['sections' => HomepageLayout::all()->map(fn (HomepageSection $s): array => self::state($s))->all()]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function state(HomepageSection $section): array
    {
        $items = (array) $section->setting('items', []);

        return [
            'key' => $section->key->value,
            'enabled' => $section->enabled,
            'limit' => $section->setting('limit'),
            'curated_ids' => $section->curated_ids ?? [],
            'leader_id' => $section->setting('leader_id'),
            'quote' => (array) $section->setting('quote', []),
            'post_id' => $section->setting('post_id'),
            'counter_items' => $section->key === SectionKey::Counters ? $items : [],
            'value_items' => $section->key === SectionKey::Values ? $items : [],
            'partner_items' => $section->key === SectionKey::Partners ? $items : [],
            'titles' => (array) $section->setting('titles', []),
            'locales' => (array) $section->setting('locales', []),
        ];
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $is = fn (SectionKey ...$keys): \Closure => fn (Get $get): bool => in_array(SectionKey::tryFrom((string) $get('key')), $keys, true);

        return $schema->components([
            Repeater::make('sections')
                ->hiddenLabel()
                ->addable(false)
                ->deletable(false)
                ->reorderableWithDragAndDrop()
                ->reorderableWithButtons()
                ->collapsible()
                ->collapsed()
                ->itemLabel(fn (array $state): string => self::itemLabel($state))
                ->schema([
                    Hidden::make('key'),
                    Toggle::make('enabled')->label(__('homepage.admin.enabled')),
                    TextInput::make('limit')
                        ->label(__('homepage.admin.limit'))
                        ->numeric()->integer()->minValue(1)
                        ->maxValue(fn (Get $get): int => SectionKey::tryFrom((string) $get('key'))?->maxItems() ?? 12)
                        ->placeholder(fn (Get $get): string => (string) (SectionKey::tryFrom((string) $get('key'))?->defaultLimit() ?? ''))
                        ->visible($is(SectionKey::Ticker, SectionKey::Hero, SectionKey::LatestNews, SectionKey::Press, SectionKey::Leagues)),
                    Select::make('curated_ids')
                        ->label(__('homepage.admin.curated'))
                        ->helperText(fn (Get $get): string => SectionKey::tryFrom((string) $get('key')) === SectionKey::Hero
                            ? __('homepage.admin.help.curated_hero') : __('homepage.admin.help.curated'))
                        ->multiple()
                        ->searchable()
                        ->maxItems(5)
                        ->options(fn (): array => self::postOptions())
                        ->visible($is(SectionKey::Ticker, SectionKey::Hero)),
                    Select::make('leader_id')
                        ->label(__('homepage.admin.leader'))
                        ->searchable()
                        ->options(fn (): array => OfficeResource::leaderOptions())
                        ->visible($is(SectionKey::Leadership)),
                    Fieldset::make(__('homepage.admin.quote'))
                        ->schema(array_map(fn (string $locale): Textarea => Textarea::make("quote.{$locale}")
                            ->label(Locales::nativeName($locale))
                            ->extraInputAttributes(['lang' => $locale])
                            ->rows(2)
                            ->maxLength(400), Locales::all()))
                        ->visible($is(SectionKey::Leadership)),
                    Select::make('post_id')
                        ->label(__('homepage.admin.full_message'))
                        ->searchable()
                        ->options(fn (): array => self::postOptions())
                        ->visible($is(SectionKey::Leadership)),
                    Repeater::make('counter_items')
                        ->label(__('homepage.admin.counters'))
                        ->maxItems(SectionKey::Counters->maxItems())
                        ->reorderableWithButtons()
                        ->schema([
                            TextInput::make('value')->label(__('homepage.admin.counter_value'))->placeholder('1,200')->required()->maxLength(20),
                            self::perLanguage('label', __('homepage.admin.counter_label'), 60),
                        ])
                        ->visible($is(SectionKey::Counters)),
                    Repeater::make('value_items')
                        ->label(__('homepage.admin.values'))
                        ->maxItems(SectionKey::Values->maxItems())
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->schema([
                            self::perLanguage('title', __('homepage.admin.value_title'), 60),
                            self::perLanguage('text', __('homepage.admin.value_text'), 240),
                        ])
                        ->visible($is(SectionKey::Values)),
                    Repeater::make('partner_items')
                        ->label(__('homepage.admin.partners'))
                        ->maxItems(SectionKey::Partners->maxItems())
                        ->reorderableWithButtons()
                        ->schema([
                            TextInput::make('url')->label(__('homepage.admin.partner_url'))->url()->startsWith(['https://'])->required()->maxLength(300),
                            self::perLanguage('label', __('homepage.admin.partner_label'), 80),
                        ])
                        ->visible($is(SectionKey::Partners)),
                    self::perLanguage('titles', __('homepage.admin.headings'), 80)
                        ->visible($is(...array_filter(SectionKey::cases(), fn (SectionKey $k): bool => ! in_array($k, [SectionKey::Ticker, SectionKey::Hero], true)))),
                    CheckboxList::make('locales')
                        ->label(__('homepage.admin.locales'))
                        ->helperText(__('homepage.admin.help.locales'))
                        ->options(collect(Locales::all())->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all())
                        ->columns(4),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label(__('homepage.admin.save'))->submit('save')->keyBindings(['mod+s']),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(CurrentUser::get()->can('homepage.manage'), 403);

        $state = $this->form->getState();
        /** @var list<array<string, mixed>> $sections */
        $sections = array_values((array) ($state['sections'] ?? []));
        app(SaveHomepage::class)->handle($sections, CurrentUser::get());

        Notification::make()->success()->title(__('homepage.admin.saved'))->send();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function itemLabel(array $state): string
    {
        $key = SectionKey::tryFrom((string) ($state['key'] ?? ''));
        if ($key === null) {
            return '';
        }

        return ($state['enabled'] ?? false) ? $key->label() : $key->label().' · '.__('homepage.admin.off');
    }

    private static function perLanguage(string $name, string $label, int $max): Fieldset
    {
        return Fieldset::make($label)
            ->schema(array_map(fn (string $locale): TextInput => TextInput::make("{$name}.{$locale}")
                ->label(Locales::nativeName($locale))
                ->extraInputAttributes(['lang' => $locale])
                ->maxLength($max), Locales::all()))
            ->columns(2);
    }

    /**
     * Recent posts, named in any language they have.
     *
     * @return array<string, string>
     */
    private static function postOptions(): array
    {
        return Post::query()->with('translations')->latest()->limit(200)->get()
            ->mapWithKeys(fn (Post $post): array => [$post->id => (string) $post->translationOrAny()?->getAttribute('title')])
            ->filter()
            ->all();
    }
}
