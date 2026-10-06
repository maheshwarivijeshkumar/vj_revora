<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Leads\Services\LeadVerifier;
use App\Jobs\Concerns\RunsInTenantContext;
use App\Models\Lead;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Re-verifies a lead with the network checks enabled (§18).
 *
 * The capture pipeline verifies inline but skips the DNS lookup, because a form
 * must not wait on somebody else's resolver. This runs straight after and adds
 * the answer, so a lead is usable immediately and accurate a second later.
 */
final class VerifyLead implements ShouldQueue
{
    use Queueable, RunsInTenantContext;

    /**
     * A resolver that is briefly unreachable is the normal failure here, and it
     * fixes itself, so a couple of retries are worth more than a dead letter.
     */
    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        private readonly int $leadId,
    ) {
        $this->captureTenant();
        $this->onQueue('verification');
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(LeadVerifier $verifier): void
    {
        $this->inTenantContext(function () use ($verifier): void {
            $lead = Lead::query()->find($this->leadId);

            if ($lead === null) {
                return;
            }

            $verifier->apply($lead, checkMx: true);
        });
    }
}
