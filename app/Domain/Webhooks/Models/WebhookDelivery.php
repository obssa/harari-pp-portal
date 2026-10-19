<?php

namespace App\Domain\Webhooks\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt history of sending an event to a webhook.
 *
 * @property int $id
 * @property int $webhook_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property ?int $response_code
 * @property ?string $error
 * @property int $attempts
 * @property ?CarbonImmutable $delivered_at
 * @property-read Webhook $webhook
 */
class WebhookDelivery extends Model
{
    protected $fillable = ['event', 'payload', 'response_code', 'error', 'attempts', 'delivered_at'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivered_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Webhook, $this>
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
