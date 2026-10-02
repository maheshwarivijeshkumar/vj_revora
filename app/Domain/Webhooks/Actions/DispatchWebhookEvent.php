<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Actions;

use App\Domain\Webhooks\Enums\DeliveryStatus;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Fans one event out to every endpoint subscribed to it (§49).
 *
 * The payload is built once and stored on the delivery, so a replay sends what
 * the subscriber was originally told rather than re-serialising a record that
 * has since changed. That is the difference between a replay and a fresh event.
 */
final class DispatchWebhookEvent
{
    /**
     * @param  array<string, mixed>  $data  the entity, already shaped the way
     *                                      the REST API presents it, so one payload format is documented once
     * @return Collection<int, WebhookDelivery>
     */
    public function handle(WebhookEvent $event, array $data): Collection
    {
        $endpoints = WebhookEndpoint::query()->listeningFor($event)->get();

        if ($endpoints->isEmpty()) {
            // No delivery row either: logging a send to nobody would make the
            // delivery log unreadable for the workspaces that do subscribe.
            return new Collection;
        }

        $payload = $this->envelope($event, $data);

        return $endpoints->map(
            fn (WebhookEndpoint $endpoint): WebhookDelivery => $this->queue($endpoint, $event, $payload),
        );
    }

    /**
     * Re-sends an earlier delivery by hand.
     *
     * A new row rather than a reset of the old one, because the original
     * attempt and its response are evidence of what happened and overwriting
     * them would destroy the answer to "did you ever send this".
     */
    public function replay(WebhookDelivery $original): WebhookDelivery
    {
        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $original->webhook_endpoint_id,
            'event' => $original->event,
            'payload' => $original->payload,
            'status' => DeliveryStatus::Pending,
            'replay_of_id' => $original->id,
        ]);

        DeliverWebhook::dispatch($delivery->id);

        return $delivery;
    }

    /**
     * Sends a sample payload to one endpoint.
     *
     * Uses lead.created with obviously fake data rather than inventing a
     * `webhook.test` event, so a subscriber exercises the same code path their
     * real handler will use instead of a shape they will never see again.
     */
    public function sendTest(WebhookEndpoint $endpoint): WebhookDelivery
    {
        return $this->queue($endpoint, WebhookEvent::LeadCreated, $this->envelope(
            WebhookEvent::LeadCreated,
            [
                'id' => '00000000-0000-7000-8000-000000000000',
                'full_name' => 'Test Lead',
                'email' => 'test@example.com',
                'status' => 'new',
                'score' => 0,
                'is_test' => true,
            ],
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function queue(
        WebhookEndpoint $endpoint,
        WebhookEvent $event,
        array $payload,
    ): WebhookDelivery {
        $delivery = WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => $event,
            'payload' => $payload,
            'status' => DeliveryStatus::Pending,
        ]);

        // Dispatched after the row exists, so the worker cannot win the race
        // and look for a delivery that has not been written yet.
        DeliverWebhook::dispatch($delivery->id);

        return $delivery;
    }

    /**
     * The envelope every event shares.
     *
     * `id` identifies the event itself rather than the entity, so a subscriber
     * can deduplicate retries. `sent_at` is when the event happened, not when
     * this attempt left, which is why it is stored rather than stamped at send
     * time.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function envelope(WebhookEvent $event, array $data): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'event' => $event->value,
            'api_version' => 'v1',
            'occurred_at' => now()->toIso8601String(),
            'data' => $data,
        ];
    }
}
