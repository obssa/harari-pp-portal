<?php

namespace App\Domain\Engagement\Enums;

/**
 * A campaign is written as a draft, may wait for its time, is sent in
 * batches and ends sent or cancelled (ADR-0052).
 */
enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Sending = 'sending';
    case Sent = 'sent';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __("campaigns.status.{$this->value}");
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'warning',
            self::Sending => 'info',
            self::Sent => 'success',
            self::Cancelled => 'danger',
        };
    }
}
