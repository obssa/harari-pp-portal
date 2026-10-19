<?php

namespace App\Domain\Organisation\Policies;

final class LeaderPolicy extends DirectoryPolicy
{
    protected function module(): string
    {
        return 'leaders';
    }
}
