<?php

namespace App\Domain\Webhooks\Listeners;

use App\Domain\Content\Events\ContentTransitioned;
use App\Domain\Webhooks\Jobs\DeliverWebhook;
use App\Domain\Webhooks\Models\Webhook;

/**
 * Queues a delivery for every active webhook subscribed to the event. The
 * payload carries ids and public facts only, never drafts' text.
 */
final class QueueWebhooks
{
    public function handle(ContentTransitioned $event): void
    {
        $name = $event->webhookEvent();
        $webhooks = Webhook::query()->where('is_active', true)->get()
            ->filter(fn (Webhook $webhook): bool => $webhook->listensTo($name));

        if ($webhooks->isEmpty()) {
            return;
        }

        $translation = $event->translation;
        $payload = [
            'event' => $name,
            'occurred_at' => now()->toIso8601String(),
            'post' => [
                'id' => $event->post->id,
                'type' => $event->post->type->value,
                'locale' => $translation->locale,
                'title' => $translation->title,
                'slug' => $translation->slug,
                'status' => $translation->status->value,
                'published_at' => $translation->published_at?->toIso8601String(),
            ],
        ];

        foreach ($webhooks as $webhook) {
            $delivery = $webhook->deliveries()->create(['event' => $name, 'payload' => $payload]);
            DeliverWebhook::dispatch($delivery);
        }
    }
}
