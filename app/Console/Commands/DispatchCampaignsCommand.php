<?php

namespace App\Console\Commands;

use App\Domain\Engagement\Actions\SendCampaign;
use App\Domain\Engagement\Enums\CampaignStatus;
use App\Domain\Engagement\Models\Campaign;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Starts the campaigns whose time has come (ADR-0052), on behalf of the
 * person who scheduled them. One that can no longer go (no text, nobody left
 * to receive it, or its scheduler lost the permission) goes back to draft.
 */
final class DispatchCampaignsCommand extends Command
{
    protected $signature = 'campaigns:dispatch';

    protected $description = 'Start scheduled campaigns that are due';

    public function handle(SendCampaign $send): int
    {
        $started = 0;
        $due = Campaign::query()->withoutGlobalScopes()->where('status', CampaignStatus::Scheduled->value)
            ->where('scheduled_at', '<=', CarbonImmutable::now())->get();
        foreach ($due as $campaign) {
            $sender = $campaign->sent_by !== null ? User::query()->find($campaign->sent_by) : null;
            try {
                abort_unless($sender instanceof User, 403);
                $send->start($campaign, $sender);
                $started++;
            } catch (ValidationException|HttpException) {
                $campaign->forceFill(['status' => CampaignStatus::Draft, 'scheduled_at' => null])->save();
            }
        }
        $this->components->info("Campaigns started: {$started}");

        return self::SUCCESS;
    }
}
