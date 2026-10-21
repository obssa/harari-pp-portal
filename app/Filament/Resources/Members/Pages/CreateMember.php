<?php

namespace App\Filament\Resources\Members\Pages;

use App\Domain\Membership\Actions\SaveMember;
use App\Domain\Membership\Models\Member;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Support\CurrentUser;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMember extends CreateRecord
{
    protected static string $resource = MemberResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveMember::class)->handle(new Member, $data, CurrentUser::get());
    }

    protected function getRedirectUrl(): string
    {
        return MemberResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
