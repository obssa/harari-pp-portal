<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Domain\Access\Models\Role;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Role $role */
        $role = $this->getRecord();
        $role->loadMissing('permissions');

        return [...$data, ...RoleForm::fillPermissions($role)];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Role $role */
        $role = $this->getRecord();

        // System role keys are fixed; only custom roles may be renamed.
        return $role->isSystem() ? [] : ['name' => $data['name'] ?? $role->name];
    }

    protected function afterSave(): void
    {
        /** @var Role $role */
        $role = $this->getRecord();
        $role->syncPermissions(RoleForm::selectedPermissions($this->form->getRawState()));
    }
}
