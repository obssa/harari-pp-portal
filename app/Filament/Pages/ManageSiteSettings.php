<?php

namespace App\Filament\Pages;

use App\Domain\Translation\Support\Locales;
use App\Filament\Support\CurrentUser;
use App\Filament\Support\ViewOnSite;
use App\Services\Calendar\EthiopianCalendar;
use App\Settings\SiteSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Site settings (PLAN.md §7). Admins can read them; only Super Admins
 * (settings.manage) can save.
 */
class ManageSiteSettings extends SettingsPage
{
    protected static string $settings = SiteSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $slug = 'settings/site';

    protected static ?int $navigationSort = 40;

    /** Social networks offered in the form, in footer order. */
    public const SOCIAL = ['facebook', 'telegram', 'youtube', 'x', 'tiktok', 'instagram'];

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.site.title');
    }

    public function getTitle(): string
    {
        return __('admin.settings.site.title');
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->can('settings.view');
    }

    public function canEdit(): bool
    {
        return CurrentUser::get()->can('settings.manage');
    }

    /**
     * Read-only viewers get no Save button.
     *
     * @return array<Action|ActionGroup>
     */
    public function getFormActions(): array
    {
        return $this->canEdit() ? parent::getFormActions() : [];
    }

    public function form(Schema $schema): Schema
    {
        $readOnly = ! $this->canEdit();
        $locales = Locales::all();

        return $schema
            ->columns(1)
            ->disabled($readOnly)
            ->components([
                Section::make(__('admin.settings.site.section.languages'))
                    ->columns(2)
                    ->schema([
                        Select::make('default_locale')
                            ->label(__('admin.settings.site.field.default_locale'))
                            ->helperText(__('admin.settings.site.field.default_locale_help'))
                            ->options(collect($locales)->mapWithKeys(fn (string $l): array => [$l => Locales::nativeName($l)])->all())
                            ->required(),
                        Radio::make('calendar_primary')
                            ->label(__('admin.settings.site.field.calendar_primary'))
                            ->options([
                                EthiopianCalendar::ETHIOPIAN => __('admin.settings.site.field.calendar_ethiopian'),
                                EthiopianCalendar::GREGORIAN => __('admin.settings.site.field.calendar_gregorian'),
                            ])
                            ->required(),
                        Toggle::make('show_secondary_calendar')
                            ->label(__('admin.settings.site.field.show_secondary_calendar')),
                    ]),

                Section::make(__('admin.settings.site.section.contact'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('contact_email')
                            ->label(__('admin.settings.site.field.contact_email'))
                            ->email()
                            ->maxLength(255),
                        TextInput::make('contact_phone')
                            ->label(__('admin.settings.site.field.contact_phone'))
                            ->tel()
                            ->regex('/^[+0-9 ()-]{7,20}$/'),
                        TextInput::make('hotline')
                            ->label(__('admin.settings.site.field.hotline'))
                            ->regex('/^[+0-9 ()-]{3,20}$/'),
                    ]),

                Section::make(__('admin.settings.site.section.address'))
                    ->description(__('admin.settings.site.section.address_help'))
                    ->columns(2)
                    ->schema(collect($locales)->flatMap(fn (string $l): array => [
                        TextInput::make("address.{$l}")
                            ->label(__('admin.settings.site.field.address', ['language' => Locales::nativeName($l)]))
                            ->maxLength(500),
                        TextInput::make("office_hours.{$l}")
                            ->label(__('admin.settings.site.field.office_hours', ['language' => Locales::nativeName($l)]))
                            ->maxLength(255),
                    ])->all()),

                Section::make(__('admin.settings.site.section.social'))
                    ->columns(2)
                    ->schema([
                        ...array_map(fn (string $network): TextInput => TextInput::make("social_links.{$network}")
                            ->label(__('admin.settings.site.social.'.$network))
                            ->url()
                            ->maxLength(500), self::SOCIAL),
                        Radio::make('facebook_feed')
                            ->label(__('admin.settings.site.field.facebook_feed'))
                            ->helperText(__('admin.settings.site.help.facebook_feed'))
                            ->options([
                                'ask' => __('admin.settings.site.facebook_feed.ask'),
                                'auto' => __('admin.settings.site.facebook_feed.auto'),
                                'off' => __('admin.settings.site.facebook_feed.off'),
                            ])
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        foreach (['address', 'office_hours', 'social_links'] as $key) {
            $data[$key] = array_filter((array) ($data[$key] ?? []), fn (mixed $v): bool => filled($v));
        }

        return $data;
    }

    /**
     * ADR-0068: open the public site to see the result.
     *
     * @return list<Action>
     */
    protected function getHeaderActions(): array
    {
        return [ViewOnSite::site()];
    }
}
