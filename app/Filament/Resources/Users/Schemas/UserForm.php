<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Domain\Access\Enums\ScopeType;
use App\Domain\Access\Enums\SystemRole;
use App\Domain\Access\Enums\UserStatus;
use App\Domain\Access\Models\Role;
use App\Filament\Support\CurrentUser;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.users.section.account'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin.users.field.name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('admin.users.field.email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('locale')
                            ->label(__('admin.users.field.locale'))
                            ->options(self::localeOptions())
                            ->placeholder(__('admin.users.field.locale_default')),
                        Select::make('status')
                            ->label(__('admin.users.field.status'))
                            ->options(collect(UserStatus::cases())->mapWithKeys(
                                fn (UserStatus $s): array => [$s->value => $s->label()],
                            )->all())
                            ->default(UserStatus::Active->value)
                            ->required()
                            ->disabled(fn (?User $record): bool => $record !== null && $record->is(self::actor())),
                        TextInput::make('password')
                            ->label(__('admin.users.field.initial_password'))
                            ->helperText(__('admin.users.field.initial_password_help'))
                            ->password()
                            ->revealable()
                            ->rule(Password::defaults())
                            ->visibleOn('create'),
                    ]),

                Section::make(__('admin.users.section.roles'))
                    ->description(__('admin.users.section.roles_help'))
                    ->visible(fn (?User $record): bool => self::actor()->can('users.assign_roles')
                        && ($record === null || self::actor()->can('assignRoles', $record)))
                    ->schema([
                        CheckboxList::make('role_names')
                            ->label(__('admin.users.field.roles'))
                            ->options(fn (): array => self::assignableRoleOptions())
                            ->columns(3)
                            ->live()
                            ->dehydrated(false),
                        Repeater::make('scopes')
                            ->label(__('admin.users.field.scopes'))
                            ->helperText(__('admin.users.field.scopes_help'))
                            ->dehydrated(false)
                            ->defaultItems(0)
                            ->columns(3)
                            ->addActionLabel(__('admin.users.field.add_scope'))
                            ->schema([
                                Select::make('role')
                                    ->label(__('admin.users.field.scope_role'))
                                    ->options(fn (Get $get): array => self::scopableRoleOptions((array) $get('../../role_names')))
                                    ->required()
                                    ->live(),
                                Select::make('scope_type')
                                    ->label(__('admin.users.field.scope_type'))
                                    ->options(fn (Get $get): array => self::scopeTypeOptions((string) $get('role')))
                                    ->required()
                                    ->live(),
                                Select::make('scope_value')
                                    ->label(__('admin.users.field.scope_value'))
                                    ->options(fn (Get $get): array => $get('scope_type') === ScopeType::Language->value
                                        ? self::localeOptions() : [])
                                    ->searchable()
                                    ->getSearchResultsUsing(fn (string $search): array => [$search => $search])
                                    ->helperText(__('admin.users.field.scope_value_help'))
                                    ->required(),
                            ]),
                    ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function localeOptions(): array
    {
        $options = [];
        foreach ((array) config('portal.locales') as $locale) {
            $options[(string) $locale] = __('access.language.'.$locale);
        }

        return $options;
    }

    /**
     * Roles the signed-in user may hand out: never Super Admin unless they are one.
     *
     * @return array<string, string>
     */
    public static function assignableRoleOptions(): array
    {
        $actor = self::actor();

        return Role::query()->orderBy('id')->get()
            ->filter(fn (Role $role): bool => $actor->can('assignRole', [User::class, $role->name]))
            ->mapWithKeys(fn (Role $role): array => [$role->name => $role->label()])
            ->all();
    }

    /**
     * @param  array<int, string>  $selected
     * @return array<string, string>
     */
    private static function scopableRoleOptions(array $selected): array
    {
        $options = [];
        foreach ($selected as $name) {
            $system = SystemRole::tryFrom($name);
            if ($system !== null && $system->allowedScopes() !== []) {
                $options[$name] = $system->label();
            }
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function scopeTypeOptions(string $role): array
    {
        $system = SystemRole::tryFrom($role);
        if ($system === null) {
            return [];
        }

        return collect($system->allowedScopes())
            ->mapWithKeys(fn (ScopeType $type): array => [$type->value => $type->label()])
            ->all();
    }

    private static function actor(): User
    {
        return CurrentUser::get();
    }
}
