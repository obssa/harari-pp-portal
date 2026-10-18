<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domain\Access\Actions\ManageAccount;
use App\Domain\Access\Actions\UpdateUserAccess;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\CurrentUser;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    private bool $shouldInvite = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // No password given: create the account with an unusable random one and
        // email a link to choose their own (the invitation).
        if (blank($data['password'] ?? null)) {
            $data['password'] = Str::password(40);
            $this->shouldInvite = true;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->getRecord();
        $actor = CurrentUser::get();

        if ($actor->can('assignRoles', $user)) {
            $state = $this->form->getRawState();
            app(UpdateUserAccess::class)(
                $actor,
                $user,
                array_values((array) ($state['role_names'] ?? [])),
                array_values((array) ($state['scopes'] ?? [])),
            );
        }

        if ($this->shouldInvite) {
            app(ManageAccount::class)->sendSignInLink($user);
        }
    }
}
