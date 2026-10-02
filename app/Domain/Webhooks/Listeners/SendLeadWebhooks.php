<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Listeners;

use App\Domain\Leads\Events\LeadCaptured;
use App\Domain\Webhooks\Actions\DispatchWebhookEvent;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Http\Resources\Api\V1\LeadResource;
use App\Models\Lead;

/**
 * Turns a capture into the webhook events it implies (§49).
 *
 * Synchronous on purpose: all it does is write delivery rows and queue jobs,
 * and queueing the fan-out as well would add a layer of failure between an
 * event happening and any record of it existing.
 */
final class SendLeadWebhooks
{
    public function __construct(
        private readonly DispatchWebhookEvent $webhooks,
    ) {}

    public function handle(LeadCaptured $event): void
    {
        $lead = $event->result->lead->loadMissing(['owner', 'source']);
        $payload = $this->payload($lead);

        // A matched duplicate enriched an existing lead; announcing it as
        // created would have subscribers insert a second record of their own.
        if ($event->result->isNew()) {
            $this->webhooks->handle(WebhookEvent::LeadCreated, $payload);
        } elseif ($event->result->wasEnriched()) {
            // Only when the repeat touch taught us something. Seeing the same
            // person again with the same details is not a change, and churning
            // subscribers for it is how a webhook feed gets ignored.
            $this->webhooks->handle(WebhookEvent::LeadUpdated, $payload);
        }

        if ($event->result->assignedTo !== null) {
            // Separate from creation because routing is what most subscribers
            // actually care about: it is the moment a human becomes
            // responsible. Emitted here rather than only in the observer,
            // because routing during capture happens on a record that is still
            // "recently created" and the observer deliberately stays quiet for
            // those saves.
            $this->webhooks->handle(WebhookEvent::LeadAssigned, $payload);
        }
    }

    /**
     * Shaped by the same resource the REST API uses, so a subscriber reading
     * the docs sees one lead format rather than two.
     *
     * @return array<string, mixed>
     */
    private function payload(Lead $lead): array
    {
        return (new LeadResource($lead))->resolve();
    }
}
