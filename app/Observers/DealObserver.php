<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Webhooks\Actions\DispatchWebhookEvent;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Http\Resources\Api\V1\DealResource;
use App\Models\Deal;

/**
 * Announces a newly opened deal (§49).
 *
 * Terminal outcomes are announced by SendDealWebhooks, which listens for the
 * stage change that caused them.
 */
final class DealObserver
{
    public function __construct(
        private readonly DispatchWebhookEvent $webhooks,
    ) {}

    public function created(Deal $deal): void
    {
        $this->webhooks->handle(
            WebhookEvent::DealCreated,
            (new DealResource($deal->loadMissing(['pipeline', 'stage', 'owner', 'company'])))->resolve(),
        );
    }
}
