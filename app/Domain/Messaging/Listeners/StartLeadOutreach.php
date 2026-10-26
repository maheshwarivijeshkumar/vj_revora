<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Listeners;

use App\Domain\Leads\Enums\LeadSourceType;
use App\Domain\Leads\Events\LeadCaptured;
use App\Domain\Messaging\Actions\EnrollInFollowUp;
use App\Jobs\SendWhatsAppWelcome;
use App\Models\Lead;
use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\DB;

/**
 * Starts contact with a new lead: the email follow-up sequence, and the
 * WhatsApp welcome template when the workspace has WhatsApp connected.
 *
 * WhatsApp's rules require the person's opt-in before a business messages
 * them, so the welcome only goes to leads that gave consent on their form
 * (§15, §88).
 */
final readonly class StartLeadOutreach
{
    public function __construct(
        private EnrollInFollowUp $enroll,
    ) {}

    public function handle(LeadCaptured $event): void
    {
        if (! $event->result->isNew()) {
            return;
        }

        $lead = $event->result->lead;

        $this->enroll->handle($lead);

        if ($this->shouldWelcomeOnWhatsApp($lead)) {
            // After commit, so the job cannot run before the lead exists.
            DB::afterCommit(fn () => SendWhatsAppWelcome::dispatch($lead->id));
        }
    }

    private function shouldWelcomeOnWhatsApp(Lead $lead): bool
    {
        if (! $lead->consent || blank($lead->phone) || $lead->whatsapp_opted_out_at !== null) {
            return false;
        }

        if ($lead->source?->type === LeadSourceType::Import) {
            return false;
        }

        $account = WhatsAppAccount::query()->first();

        return $account !== null && $account->auto_send_welcome && filled($account->welcome_template);
    }
}
