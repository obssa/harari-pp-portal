<?php

namespace App\Filament\Pages;

use App\Filament\Support\CurrentUser;
use App\Settings\MembershipSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * How people who apply to join confirm their contact details (ADR-0067):
 * a code by SMS, a code by email, or no code. Seen by those who may view
 * settings, changed by those who may manage them.
 */
class ManageMembershipSettings extends SettingsPage
{
    protected static string $settings = MembershipSettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $slug = 'settings/membership';

    protected static ?int $navigationSort = 41;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.membership.title');
    }

    public function getTitle(): string
    {
        return __('admin.settings.membership.title');
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
        return $schema
            ->columns(1)
            ->disabled(! $this->canEdit())
            ->components([
                Section::make(__('admin.settings.membership.section.verification'))
                    ->description(__('admin.settings.membership.help.verification'))
                    ->schema([
                        Radio::make('verification')
                            ->label(__('admin.settings.membership.field.verification'))
                            ->options(collect(MembershipSettings::VERIFICATIONS)->mapWithKeys(fn (string $method): array => [$method => __("admin.settings.membership.verification.{$method}")])->all())
                            ->descriptions(collect(MembershipSettings::VERIFICATIONS)->mapWithKeys(fn (string $method): array => [$method => __("admin.settings.membership.verification_help.{$method}")])->all())
                            ->required(),
                    ]),
            ]);
    }
}
