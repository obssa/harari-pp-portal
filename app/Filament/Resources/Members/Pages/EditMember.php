<?php

namespace App\Filament\Resources\Members\Pages;

use App\Domain\Membership\Actions\SaveMember;
use App\Domain\Membership\Models\Member;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Support\CurrentUser;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    /**
     * Personal details are hidden from the model's array form; the form gets
     * them here, except the ID number, which is only replaced when typed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Member $member */
        $member = $this->getRecord();

        return [...$data, 'name' => $member->name, 'birth_date' => $member->birth_date, 'phone' => $member->phone,
            'email' => $member->email, 'address_note' => $member->address_note, 'id_number' => null];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Member $record */
        return app(SaveMember::class)->handle($record, $data, CurrentUser::get());
    }

    protected function getRedirectUrl(): string
    {
        return MemberResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
