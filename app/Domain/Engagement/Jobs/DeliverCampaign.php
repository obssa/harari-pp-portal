<?php

namespace App\Domain\Engagement\Jobs;

use App\Domain\Engagement\Actions\SendCampaign;
use App\Domain\Engagement\Enums\CampaignStatus;
use App\Domain\Engagement\Models\Campaign;
use App\Domain\Engagement\Models\CampaignDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends one minute's worth of a campaign (ADR-0052), then queues itself for
 * the next minute until every recipient has had their message.
 */
final class DeliverCampaign implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public readonly int $campaignId) {}

    public function handle(SendCampaign $send): void
    {
        $campaign = Campaign::query()->withoutGlobalScopes()->find($this->campaignId);
        if (! $campaign instanceof Campaign || $campaign->status !== CampaignStatus::Sending) {
            return;
        }

        $limit = max(1, (int) config($campaign->channel === Campaign::EMAIL ? 'portal.campaign_email_per_minute' : 'portal.campaign_sms_per_minute'));
        $batch = $campaign->deliveries()->where('status', CampaignDelivery::QUEUED)->orderBy('id')->limit($limit)->get();
        foreach ($batch as $delivery) {
            $delivery->setRelation('campaign', $campaign);
            $send->deliver($delivery);
        }

        if ($campaign->deliveries()->where('status', CampaignDelivery::QUEUED)->exists()) {
            self::dispatch($campaign->id)->delay(CarbonImmutable::now()->addMinute());

            return;
        }
        $campaign->forceFill(['status' => CampaignStatus::Sent, 'finished_at' => CarbonImmutable::now()])->save();
    }
}
