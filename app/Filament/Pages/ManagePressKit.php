<?php

namespace App\Filament\Pages;

use App\Domain\Forms\Enums\FormType;
use App\Domain\Forms\Models\Form;
use App\Domain\Media\Enums\MediaKind;
use App\Domain\Translation\Support\Locales;
use App\Filament\Support\MediaPicker;
use App\Settings\PressKitSettings;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The press kit (PLAN.md §4, ADR-0029): the short description of the party
 * office in each language, the media contact, the logos to download and the
 * press enquiry form. Portraits come from the leadership directory.
 */
class ManagePressKit extends SettingsPage
{
    protected static string $settings = PressKitSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static ?string $slug = 'press-kit';

    protected static ?int $navigationSort = 25;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.content');
    }

    public static function getNavigationLabel(): string
    {
        return __('press.admin.title');
    }

    public function getTitle(): string
    {
        return __('press.admin.title');
    }

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->can('press_kit.manage');
    }

    public function form(Schema $schema): Schema
    {
        $locales = Locales::all();

        return $schema->columns(1)->components([
            Section::make(__('press.admin.section.boilerplate'))
                ->description(__('press.admin.help.boilerplate'))
                ->schema(array_map(fn (string $l): Textarea => Textarea::make("boilerplate.{$l}")
                    ->label(Locales::nativeName($l))->rows(4)->maxLength(2000)->extraInputAttributes(['lang' => $l]), $locales)),
            Section::make(__('press.admin.section.contact'))->columns(2)->schema([
                ...array_map(fn (string $l): TextInput => TextInput::make("contact_name.{$l}")
                    ->label(__('press.admin.field.contact_name', ['language' => Locales::nativeName($l)]))->maxLength(200), $locales),
                TextInput::make('contact_email')->label(__('press.admin.field.contact_email'))->email()->maxLength(190),
                TextInput::make('contact_phone')->label(__('press.admin.field.contact_phone'))->tel()->maxLength(30),
                Select::make('form_id')->label(__('press.admin.field.form'))->helperText(__('press.admin.help.form'))
                    ->options(fn (): array => Form::query()->where('type', FormType::Press->value)->where('active', true)->with('translations')->get()
                        ->mapWithKeys(fn (Form $f): array => [$f->id => $f->title()])->all()),
            ]),
            Section::make(__('press.admin.section.logos'))
                ->description(__('press.admin.help.logos'))
                ->schema([MediaPicker::make('logo_ids', [MediaKind::Image, MediaKind::Vector], multiple: true)->label(__('press.admin.field.logos'))]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        foreach (['boilerplate', 'contact_name'] as $key) {
            $data[$key] = array_filter((array) ($data[$key] ?? []), fn (mixed $v): bool => filled($v));
        }
        $data['logo_ids'] = array_values(array_map('intval', array_filter((array) ($data['logo_ids'] ?? []), 'is_numeric')));
        $data['form_id'] = filled($data['form_id'] ?? null) ? (string) $data['form_id'] : null;

        return $data;
    }
}
