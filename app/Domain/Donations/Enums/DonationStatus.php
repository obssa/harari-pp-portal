<?php

namespace App\Domain\Donations\Enums;

enum DonationStatus: string
{
    case Pledged = 'pledged';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('donations.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Pledged => 'warning',
            self::Received => 'success',
            self::Cancelled => 'gray',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
