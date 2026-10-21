<?php

namespace App\Filament\Pages;

use App\Domain\Translation\Support\Locales;
use App\Settings\DonationSettings;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Donations settings (ADR-0060), Super Admin only: the switch that opens the
 * donate page once the party and the law allow it, the accounts to transfer
 * to and the texts per language.
 */
class ManageDonations extends SettingsPage
{
    protected static string $settings = DonationSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $slug = 'settings/donations';

    protected static ?int $navigationSort = 43;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('donations.settings.title');
    }

    public function getTitle(): string
    {
        return __('donations.settings.title');
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->can('settings.manage');
    }

    public function form(Schema $schema): Schema
    {
        $locales = Locales::all();

        return $schema->columns(1)->components([
            Section::make(__('donations.settings.section.switch'))
                ->description(__('donations.settings.switch_help'))
                ->columns(3)
                ->schema([
                    Toggle::make('enabled')->label(__('donations.settings.enabled'))->columnSpanFull(),
                    TextInput::make('currency')->label(__('donations.settings.currency'))->required()->length(3)->default('ETB'),
                    TextInput::make('minimum')->label(__('donations.settings.minimum'))->integer()->minValue(1)->required(),
                ]),
            Section::make(__('donations.settings.section.accounts'))
                ->description(__('donations.settings.accounts_help'))
                ->schema([
                    Repeater::make('accounts')->hiddenLabel()->columns(3)->defaultItems(0)
                        ->addActionLabel(__('donations.settings.add_account'))
                        ->schema([
                            TextInput::make('bank')->label(__('donations.settings.bank'))->required()->maxLength(120),
                            TextInput::make('name')->label(__('donations.settings.account_name'))->required()->maxLength(120),
                            TextInput::make('number')->label(__('donations.settings.account_number'))->required()->maxLength(40),
                        ]),
                ]),
            Section::make(__('donations.settings.section.texts'))
                ->description(__('donations.settings.texts_help'))
                ->columns(2)
                ->schema([
                    ...array_map(fn (string $l): Textarea => Textarea::make("intro.{$l}")
                        ->label(__('donations.settings.intro', ['language' => Locales::nativeName($l)]))
                        ->rows(3)->maxLength(1000)->extraInputAttributes(['lang' => $l]), $locales),
                    ...array_map(fn (string $l): Textarea => Textarea::make("instructions.{$l}")
                        ->label(__('donations.settings.instructions', ['language' => Locales::nativeName($l)]))
                        ->rows(4)->maxLength(2000)->extraInputAttributes(['lang' => $l]), $locales),
                ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        foreach (['intro', 'instructions'] as $key) {
            $data[$key] = array_filter((array) ($data[$key] ?? []), fn (mixed $v): bool => filled($v));
        }
        $data['accounts'] = array_values(array_map(fn (array $a): array => [
            'bank' => trim((string) ($a['bank'] ?? '')),
            'name' => trim((string) ($a['name'] ?? '')),
            'number' => trim((string) ($a['number'] ?? '')),
        ], array_filter((array) ($data['accounts'] ?? []), 'is_array')));
        $data['currency'] = strtoupper((string) ($data['currency'] ?? 'ETB'));
        $data['minimum'] = max(1, (int) ($data['minimum'] ?? 1));

        return $data;
    }
}
