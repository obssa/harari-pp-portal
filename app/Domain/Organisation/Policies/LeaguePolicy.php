<?php

namespace App\Domain\Organisation\Policies;

final class LeaguePolicy extends DirectoryPolicy
{
    protected function module(): string
    {
        return 'leagues';
    }
}
