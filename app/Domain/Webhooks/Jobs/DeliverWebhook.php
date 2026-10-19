<?php

namespace App\Domain\Webhooks\Jobs;

use App\Domain\Webhooks\Models\WebhookDelivery;
use App\Domain\Webhooks\Support\WebhookUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Sends one event to one webhook: JSON body, `X-Portal-Event` and
 * `X-Portal-Signature: sha256=<HMAC of the body with the secret>`, a short
 * timeout, three attempts with growing pauses, every attempt logged.
 */
final class DeliverWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly WebhookDelivery $delivery) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $delivery = $this->delivery->loadMissing('webhook');
        $webhook = $delivery->webhook;
        $delivery->increment('attempts');

        if (! $webhook->is_active || ! WebhookUrl::isAllowed($webhook->url)) {
            $delivery->update(['error' => 'Webhook inactive or address not allowed.']);

            return;
        }

        $body = (string) json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        try {
            $response = Http::timeout(10)
                ->withoutRedirecting()
                ->withHeaders([
                    'X-Portal-Event' => $delivery->event,
                    'X-Portal-Delivery' => (string) $delivery->id,
                    'X-Portal-Signature' => 'sha256='.hash_hmac('sha256', $body, $webhook->secret),
                    'User-Agent' => 'HarariPortal-Webhooks/1.0',
                ])
                ->withBody($body, 'application/json')
                ->post($webhook->url);
        } catch (Throwable $e) {
            $delivery->update(['error' => mb_substr($e->getMessage(), 0, 500)]);
            $this->release($this->backoff()[$this->attempts() - 1] ?? 300);

            return;
        }

        $delivery->update([
            'response_code' => $response->status(),
            'error' => $response->successful() ? null : mb_substr((string) $response->body(), 0, 500),
            'delivered_at' => $response->successful() ? now() : null,
        ]);

        if ($response->successful()) {
            $webhook->forceFill(['last_delivered_at' => now()])->save();

            return;
        }

        if ($response->serverError() && $this->attempts() < $this->tries) {
            $this->release($this->backoff()[$this->attempts() - 1] ?? 300);
        }
    }
}
