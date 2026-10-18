<?php

namespace App\Domain\Access\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return __('access.status.'.$this->value);
    }
}
