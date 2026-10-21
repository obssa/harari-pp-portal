<?php

namespace App\Domain\Engagement\Actions;

use App\Domain\Engagement\Enums\CampaignStatus;
use App\Domain\Engagement\Jobs\DeliverCampaign;
use App\Domain\Engagement\Mail\CampaignMail;
use App\Domain\Engagement\Models\Campaign;
use App\Domain\Engagement\Models\CampaignDelivery;
use App\Domain\Engagement\Models\Subscriber;
use App\Domain\Engagement\Support\Audience;
use App\Domain\Membership\Models\Member;
use App\Domain\Volunteers\Models\Volunteer;
use App\Models\User;
use App\Services\Sms\SmsGateway;
use App\Support\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sending a campaign (ADR-0052): a test to oneself, now or at a set time, in
 * batches within the sending limits. Each recipient is checked again just
 * before their message, so someone who unsubscribed meanwhile is skipped.
 */
final class SendCampaign
{
    /** Sends the campaign to the sender only, in the first written language or the one asked for. */
    public function test(Campaign $campaign, User $actor, ?string $phone = null, ?string $locale = null): void
    {
        abort_unless($actor->can('campaigns.manage') || $actor->can('campaigns.send'), 403);
        $language = $campaign->languageFor($locale ?? $actor->locale ?? 'en');
        if ($language === null) {
            throw ValidationException::withMessages(['content' => __('campaigns.validation.no_text')]);
        }
        if ($campaign->channel === Campaign::EMAIL) {
            Mail::to($actor->email)->locale($language)->send(new CampaignMail($campaign, $language, null));

            return;
        }
        $to = PhoneNumber::normalise((string) $phone);
        if ($to === null) {
            throw ValidationException::withMessages(['phone' => __('events.registration.validation.phone')]);
        }
        app(SmsGateway::class)->send($to, self::smsText($campaign, $language, null));
    }

    public function schedule(Campaign $campaign, CarbonImmutable $at, User $actor): void
    {
        $this->assertReady($campaign, $actor);
        if ($at->isPast()) {
            throw ValidationException::withMessages(['scheduled_at' => __('campaigns.validation.past')]);
        }
        $campaign->forceFill(['status' => CampaignStatus::Scheduled, 'scheduled_at' => $at, 'sent_by' => $actor->id])->save();
    }

    /** Builds the list of recipients and hands the sending to the queue. */
    public function start(Campaign $campaign, User $actor): int
    {
        $this->assertReady($campaign, $actor);

        $count = DB::transaction(function () use ($campaign, $actor): int {
            $campaign->forceFill(['status' => CampaignStatus::Sending, 'started_at' => CarbonImmutable::now(), 'sent_by' => $actor->id])->save();
            $rows = [];
            $count = 0;
            $now = CarbonImmutable::now();
            foreach ((new Audience($actor))->recipients($campaign) as $recipient) {
                $rows[] = [
                    'campaign_id' => $campaign->id, 'recipient_type' => $recipient['type'], 'recipient_id' => $recipient['id'],
                    'locale' => $recipient['locale'], 'address_hash' => $recipient['hash'], 'token' => CampaignDelivery::newToken(),
                    'status' => CampaignDelivery::QUEUED, 'created_at' => $now, 'updated_at' => $now,
                ];
                if (count($rows) === 500) {
                    $count += DB::table('campaign_deliveries')->insertOrIgnore($rows);
                    $rows = [];
                }
            }

            return $count + ($rows === [] ? 0 : DB::table('campaign_deliveries')->insertOrIgnore($rows));
        });

        activity('campaigns')->performedOn($campaign)->causedBy($actor)->event('sent')->withProperties(['recipients' => $count])->log('Campaign sent');
        DeliverCampaign::dispatch($campaign->id);

        return $count;
    }

    /** A scheduled campaign goes back to draft; one being sent stops, the rest is skipped. */
    public function cancel(Campaign $campaign, User $actor): void
    {
        abort_unless($actor->can('campaigns.send'), 403);
        if ($campaign->status === CampaignStatus::Scheduled) {
            $campaign->forceFill(['status' => CampaignStatus::Draft, 'scheduled_at' => null])->save();
        } elseif ($campaign->status === CampaignStatus::Sending) {
            $campaign->deliveries()->where('status', CampaignDelivery::QUEUED)->update(['status' => CampaignDelivery::SKIPPED, 'error' => 'cancelled']);
            $campaign->forceFill(['status' => CampaignStatus::Cancelled, 'finished_at' => CarbonImmutable::now()])->save();
        }
        activity('campaigns')->performedOn($campaign)->causedBy($actor)->event('cancelled')->log('Campaign cancelled');
    }

    /** One message to one recipient, from the queue. */
    public function deliver(CampaignDelivery $delivery): void
    {
        $campaign = $delivery->campaign;
        $person = $delivery->recipient();
        $address = $person === null ? null : ($campaign->channel === Campaign::EMAIL ? $person->email : $person->phone);
        $language = $campaign->languageFor($delivery->locale);
        if ($person === null || $address === null || $language === null || ! self::stillWanted($person, $campaign->channel)) {
            $delivery->forceFill(['status' => CampaignDelivery::SKIPPED])->save();

            return;
        }

        try {
            if ($campaign->channel === Campaign::EMAIL) {
                Mail::to($address)->locale($language)->send(new CampaignMail($campaign, $language, $delivery));
            } else {
                app(SmsGateway::class)->send($address, self::smsText($campaign, $language, $delivery));
            }
            $delivery->forceFill(['status' => CampaignDelivery::SENT, 'sent_at' => CarbonImmutable::now(), 'error' => null])->save();
        } catch (Throwable $e) {
            $delivery->forceFill(['status' => CampaignDelivery::FAILED, 'error' => mb_substr($e->getMessage(), 0, 190)])->save();
        }
    }

    public static function smsText(Campaign $campaign, string $language, ?CampaignDelivery $delivery): string
    {
        return trim((string) ($campaign->content[$language]['text'] ?? '')).' '.self::stopLine($language, $delivery->token ?? str_repeat('x', 12));
    }

    /** "Stop: https://…/u/abc123", added to every campaign SMS. */
    public static function stopLine(string $language, string $token): string
    {
        return __('campaigns.sms.stop', ['url' => route('campaigns.unsubscribe', ['locale' => $language, 'token' => $token])], $language);
    }

    /** Whether the person still wants campaigns on this channel. */
    public static function stillWanted(Subscriber|Member|Volunteer $person, string $channel): bool
    {
        if ($person instanceof Subscriber) {
            return $person->reachableBy($channel);
        }

        return $person->erased_at === null && $person->campaigns_opt_out_at === null;
    }

    private function assertReady(Campaign $campaign, User $actor): void
    {
        abort_unless($actor->can('campaigns.send'), 403);
        if (! in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Scheduled], true)) {
            throw ValidationException::withMessages(['status' => __('campaigns.validation.not_draft')]);
        }
        if ($campaign->languages() === []) {
            throw ValidationException::withMessages(['content' => __('campaigns.validation.no_text')]);
        }
        $audience = new Audience($actor);
        if (! $audience->allowed((string) ($campaign->audience['type'] ?? '')) || $audience->count($campaign) === 0) {
            throw ValidationException::withMessages(['audience' => __('campaigns.validation.no_audience')]);
        }
    }
}
