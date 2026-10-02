<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Listeners;

use App\Domain\Deals\Events\DealStageChanged;
use App\Domain\Webhooks\Actions\DispatchWebhookEvent;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Http\Resources\Api\V1\DealResource;
use App\Models\PipelineStage;

/**
 * Announces a deal reaching a terminal stage (§49).
 *
 * Only won and lost are emitted. Every intermediate move is visible on the
 * board already, and a subscriber notified of all of them would have to filter
 * out almost everything it received.
 */
final class SendDealWebhooks
{
    public function __construct(
        private readonly DispatchWebhookEvent $webhooks,
    ) {}

    public function handle(DealStageChanged $event): void
    {
        $stage = PipelineStage::query()->find($event->toStageId);

        if (! $stage instanceof PipelineStage) {
            return;
        }

        // Read from the stage rather than the deal's status, because the stage
        // is what defines the outcome and the status is derived from it (§22).
        $webhookEvent = match (true) {
            $stage->is_won => WebhookEvent::DealWon,
            $stage->is_lost => WebhookEvent::DealLost,
            default => null,
        };

        if ($webhookEvent === null) {
            return;
        }

        $deal = $event->deal->loadMissing(['pipeline', 'stage', 'owner', 'company']);

        $this->webhooks->handle($webhookEvent, (new DealResource($deal))->resolve());
    }
}
