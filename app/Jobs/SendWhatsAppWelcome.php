<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Messaging\Actions\SendWhatsAppToLead;
use App\Domain\Messaging\Exceptions\CannotMessageLead;
use App\Jobs\Concerns\RunsInTenantContext;
use App\Models\Lead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sends the approved WhatsApp welcome template to a new lead (§15).
 */
final class SendWhatsAppWelcome implements ShouldQueue
{
    use Queueable, RunsInTenantContext;

    public int $tries = 3;

    public function __construct(
        private readonly int $leadId,
    ) {
        $this->captureTenant();
        $this->onQueue('messaging');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(SendWhatsAppToLead $send): void
    {
        $this->inTenantContext(function () use ($send): void {
            $lead = Lead::query()->find($this->leadId);

            if ($lead === null) {
                return;
            }

            try {
                $send->template($lead);
            } catch (CannotMessageLead|ProviderAuthException) {
                // Nothing a retry can fix: no number, opted out, or the token
                // needs replacing (the settings page says so).
                return;
            }
        });
    }
}
