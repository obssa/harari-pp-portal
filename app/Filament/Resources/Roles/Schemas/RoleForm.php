<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Domain\Access\Enums\SystemRole;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Support\PermissionRegistry;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Role × permission matrix: one checkbox row per module (PLAN.md §7
 * "Roles & permissions: matrix editor"). Permissions are saved by the page
 * through syncPermissions() so every change is audited.
 */
class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        $sections = [];
        foreach (PermissionRegistry::modules() as $module => $permissions) {
            $sections[] = CheckboxList::make('permissions_'.$module)
                ->label(PermissionRegistry::moduleLabel($module))
                ->options(collect($permissions)->mapWithKeys(
                    fn (string $p): array => [$p => PermissionRegistry::label($p)],
                )->all())
                ->columns(4)
                ->gridDirection('row')
                ->bulkToggleable()
                ->dehydrated(false)
                ->disabled(fn (?Role $record): bool => (bool) $record?->isSuperAdmin());
        }

        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.roles.section.role'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('admin.roles.field.key'))
                            ->helperText(__('admin.roles.field.key_help'))
                            ->required()
                            ->maxLength(64)
                            ->regex('/^[a-z][a-z0-9_]*$/')
                            ->unique(ignoreRecord: true)
                            // New roles may not reuse a built-in key; built-in roles keep theirs.
                            ->notIn(fn (?Role $record): array => $record === null
                                ? array_map(fn (SystemRole $r): string => $r->value, SystemRole::cases())
                                : [])
                            ->disabled(fn (?Role $record): bool => (bool) $record?->isSystem()),
                    ]),
                Section::make(__('admin.roles.section.permissions'))
                    ->description(__('admin.roles.section.permissions_help'))
                    ->schema($sections),
            ]);
    }

    /**
     * Flattens the per-module checkbox state into permission names that exist.
     *
     * @param  array<string, mixed>  $state
     * @return list<string>
     */
    public static function selectedPermissions(array $state): array
    {
        $selected = [];
        foreach (array_keys(PermissionRegistry::modules()) as $module) {
            foreach ((array) ($state['permissions_'.$module] ?? []) as $permission) {
                if (is_string($permission) && PermissionRegistry::exists($permission)) {
                    $selected[] = $permission;
                }
            }
        }

        return array_values(array_unique($selected));
    }

    /**
     * @return array<string, list<string>>
     */
    public static function fillPermissions(Role $role): array
    {
        $granted = $role->permissions->pluck('name')->all();
        $data = [];
        foreach (PermissionRegistry::modules() as $module => $permissions) {
            $data['permissions_'.$module] = array_values(array_intersect($permissions, $granted));
        }

        return $data;
    }
}
