<?php

namespace App\Domain\Engagement\Policies;

use App\Domain\Engagement\Enums\CampaignStatus;
use App\Domain\Engagement\Models\Campaign;
use App\Models\User;

/**
 * Campaigns (ADR-0052): seen with `campaigns.view`, written as drafts with
 * `campaigns.manage`; a campaign that went out is never changed or deleted.
 * Sending needs `campaigns.send` and is checked by the send action.
 */
final class CampaignPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can('campaigns.view');
    }

    public function view(User $actor, Campaign $campaign): bool
    {
        return $actor->can('campaigns.view');
    }

    public function create(User $actor): bool
    {
        return $actor->can('campaigns.manage');
    }

    public function update(User $actor, Campaign $campaign): bool
    {
        return $actor->can('campaigns.manage') && $campaign->status === CampaignStatus::Draft;
    }

    public function delete(User $actor, Campaign $campaign): bool
    {
        return $actor->can('campaigns.manage') && $campaign->status === CampaignStatus::Draft;
    }

    public function deleteAny(User $actor): bool
    {
        return false;
    }
}
