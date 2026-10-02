<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Webhooks\Actions\DispatchWebhookEvent;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Http\Resources\Api\V1\LeadResource;
use App\Models\Lead;

/**
 * Announces changes to a lead after it exists (§49).
 *
 * Creation and routing-at-capture are announced by SendLeadWebhooks, which runs
 * at the end of the capture pipeline where the score and owner are final. This
 * covers everything afterwards, whoever made the change — UI, API or import —
 * so a subscriber cannot be told about an edit from one door and not another.
 */
final class LeadObserver
{
    public function __construct(
        private readonly DispatchWebhookEvent $webhooks,
    ) {}

    public function updated(Lead $lead): void
    {
        // The capture pipeline announces itself once, through LeadCaptured, and
        // marks the lead while it is still scoring and routing it. Reacting to
        // those saves would follow every lead.created with a meaningless
        // lead.updated, and would announce one enrichment twice.
        if ($lead->isBeingCaptured) {
            return;
        }

        $payload = (new LeadResource($lead->loadMissing(['owner', 'source'])))->resolve();

        if ($lead->wasChanged('status') && $lead->status === LeadStatus::Qualified) {
            $this->webhooks->handle(WebhookEvent::LeadQualified, $payload);
        }

        if ($lead->wasChanged('owner_id') && $lead->owner_id !== null) {
            $this->webhooks->handle(WebhookEvent::LeadAssigned, $payload);
        }

        // Emitted alongside the specific event, not instead of it: a subscriber
        // mirroring leads into its own database subscribes to this one alone and
        // would otherwise miss a qualify or a reassignment entirely.
        $this->webhooks->handle(WebhookEvent::LeadUpdated, $payload);
    }
}
