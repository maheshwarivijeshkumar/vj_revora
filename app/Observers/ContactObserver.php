<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Webhooks\Actions\DispatchWebhookEvent;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Http\Resources\Api\V1\ContactResource;
use App\Models\Contact;

/**
 * Announces a new person (§49).
 */
final class ContactObserver
{
    public function __construct(
        private readonly DispatchWebhookEvent $webhooks,
    ) {}

    public function created(Contact $contact): void
    {
        $this->webhooks->handle(
            WebhookEvent::ContactCreated,
            // Companies are loaded even though they are attached a moment later
            // in the same transaction, so the payload has the shape a caller
            // expects rather than silently omitting the key.
            (new ContactResource($contact->loadMissing(['owner', 'companies'])))->resolve(),
        );
    }
}
