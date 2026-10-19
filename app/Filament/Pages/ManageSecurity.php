<?php

namespace App\Filament\Pages;

use App\Domain\Access\Enums\SystemRole;
use App\Filament\Support\CurrentUser;
use App\Models\User;
use App\Settings\SecuritySettings;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Sign-in rules (ADR-0018, Super Admin only): whether two-step sign-in is
 * compulsory for Super Admins, Admins and Publishers. Anyone may still turn
 * it on for their own account from their profile.
 */
class ManageSecurity extends SettingsPage
{
    protected static string $settings = SecuritySettings::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $slug = 'settings/security';

    protected static ?int $navigationSort = 42;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.administration');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.security.title');
    }

    public function getTitle(): string
    {
        return __('admin.settings.security.title');
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isSuperAdmin();
    }

    public function canEdit(): bool
    {
        return CurrentUser::get()->isSuperAdmin();
    }

    public function form(Schema $schema): Schema
    {
        $roles = collect((array) config('portal.mfa_required_roles'))
            ->map(fn (string $role): string => SystemRole::tryFrom($role)?->label() ?? $role)
            ->implode(', ');

        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.settings.security.section.mfa'))
                    ->description(__('admin.settings.security.help.mfa'))
                    ->schema([
                        Toggle::make('require_mfa')
                            ->label(__('admin.settings.security.field.require_mfa'))
                            ->helperText(__('admin.settings.security.help.require_mfa', ['roles' => $roles])),
                        TextEntry::make('without_app')
                            ->label(__('admin.settings.security.field.without_app'))
                            ->state(fn (): string => (string) self::coveredWithoutApp())
                            ->helperText(__('admin.settings.security.help.without_app')),
                    ]),
            ]);
    }

    /** People in the covered roles who have not set up an authenticator app yet. */
    public static function coveredWithoutApp(): int
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', (array) config('portal.mfa_required_roles')))
            ->whereNull('app_authentication_secret')
            ->count();
    }
}
