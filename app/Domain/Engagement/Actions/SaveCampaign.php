<?php

namespace App\Domain\Engagement\Actions;

use App\Domain\Engagement\Enums\CampaignStatus;
use App\Domain\Engagement\Models\Campaign;
use App\Domain\Engagement\Support\Audience;
use App\Domain\Engagement\Support\SmsLength;
use App\Domain\Engagement\Support\SubscriptionOptions;
use App\Domain\Membership\Enums\MemberStatus;
use App\Domain\Organisation\Enums\OfficeType;
use App\Domain\Organisation\Models\Office;
use App\Domain\Translation\Support\Locales;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Writes a campaign draft (ADR-0052): name, channel, audience and the text in
 * each language. Only drafts change; a sent campaign stays as it went out.
 */
final class SaveCampaign
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Campaign $campaign, array $data, User $actor): Campaign
    {
        abort_unless($actor->can('campaigns.manage'), 403);
        if ($campaign->exists && $campaign->status !== CampaignStatus::Draft) {
            throw ValidationException::withMessages(['data.name' => __('campaigns.validation.not_draft')]);
        }

        $errors = [];
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 120) {
            $errors['data.name'] = __('campaigns.validation.name');
        }
        $channel = (string) ($data['channel'] ?? '');
        if (! in_array($channel, [Campaign::EMAIL, Campaign::SMS], true)) {
            $errors['data.channel'] = __('campaigns.validation.channel');
        }

        $input = (array) ($data['audience'] ?? []);
        $type = (string) ($input['type'] ?? '');
        if (! (new Audience($actor))->allowed($type)) {
            $errors['data.audience.type'] = __('campaigns.validation.audience');
        }
        $audience = [
            'type' => $type,
            'topics' => array_values(array_intersect(SubscriptionOptions::TOPICS, (array) ($input['topics'] ?? []))),
            'locales' => array_values(array_intersect(Locales::all(), (array) ($input['locales'] ?? []))),
            'woredas' => Office::query()->where('type', OfficeType::Woreda->value)->whereKey(array_map('strval', (array) ($input['woredas'] ?? [])))->pluck('id')->values()->all(),
            'statuses' => array_values(array_intersect(array_map(fn (MemberStatus $s): string => $s->value, MemberStatus::cases()), (array) ($input['statuses'] ?? []))),
        ];

        $content = [];
        foreach (Locales::all() as $locale) {
            $text = (array) (($data['content'] ?? [])[$locale] ?? []);
            if ($channel === Campaign::EMAIL) {
                $subject = trim((string) ($text['subject'] ?? ''));
                $body = trim((string) ($text['body'] ?? ''));
                if (mb_strlen($subject) > 150) {
                    $errors["data.content.{$locale}.subject"] = __('forms.validation.too_long', ['max' => 150]);
                }
                if (mb_strlen($body) > 20000) {
                    $errors["data.content.{$locale}.body"] = __('forms.validation.too_long', ['max' => 20000]);
                }
                $content[$locale] = ['subject' => $subject !== '' ? $subject : null, 'body' => $body !== '' ? $body : null];
            } else {
                $sms = trim((string) ($text['text'] ?? ''));
                if ($sms !== '' && SmsLength::measure($sms.' '.SendCampaign::stopLine($locale, str_repeat('x', 12)))['parts'] > SmsLength::MAX_PARTS) {
                    $errors["data.content.{$locale}.text"] = __('campaigns.validation.sms_length', ['parts' => SmsLength::MAX_PARTS]);
                }
                $content[$locale] = ['text' => $sms !== '' ? $sms : null];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $campaign->fill(['name' => $name, 'channel' => $channel, 'audience' => $audience, 'content' => $content]);
        if (! $campaign->exists) {
            $campaign->status = CampaignStatus::Draft;
            $campaign->created_by = $actor->id;
        }
        $campaign->save();

        return $campaign;
    }
}
