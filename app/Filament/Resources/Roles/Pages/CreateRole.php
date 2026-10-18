<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Domain\Access\Models\Role;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ['name' => $data['name'], 'guard_name' => 'web'];
    }

    protected function afterCreate(): void
    {
        /** @var Role $role */
        $role = $this->getRecord();
        $role->syncPermissions(RoleForm::selectedPermissions($this->form->getRawState()));
    }
}
