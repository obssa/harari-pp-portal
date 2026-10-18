<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domain\Access\Actions\UpdateUserAccess;
use App\Domain\Access\Models\RoleScope;
use App\Filament\Resources\Users\Actions\UserActions;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\CurrentUser;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                UserActions::sendSignInLink(),
                UserActions::suspend(),
                UserActions::reactivate(),
                UserActions::resetMfa(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->getRecord();

        $data['role_names'] = $user->roles->pluck('name')->all();
        $data['scopes'] = $user->roleScopes->map(fn (RoleScope $scope): array => [
            'role' => $scope->role->name,
            'scope_type' => $scope->scope_type->value,
            'scope_value' => $scope->scope_value,
        ])->all();

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var User $user */
        $user = $this->getRecord();
        $actor = CurrentUser::get();

        if (! $actor->can('assignRoles', $user)) {
            return;
        }

        $state = $this->form->getRawState();
        app(UpdateUserAccess::class)(
            $actor,
            $user,
            array_values((array) ($state['role_names'] ?? [])),
            array_values((array) ($state['scopes'] ?? [])),
        );
    }
}
