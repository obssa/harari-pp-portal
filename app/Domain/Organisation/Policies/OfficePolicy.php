<?php

namespace App\Domain\Organisation\Policies;

use App\Domain\Organisation\Models\Office;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

final class OfficePolicy extends DirectoryPolicy
{
    protected function module(): string
    {
        return 'offices';
    }

    /** An office with sub-offices stays until they are moved or removed. */
    public function delete(User $actor, Model $record): bool
    {
        return parent::delete($actor, $record)
            && $record instanceof Office
            && $record->children()->doesntExist();
    }
}
