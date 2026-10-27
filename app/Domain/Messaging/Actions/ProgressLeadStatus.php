<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Leads\Enums\LeadStatus;
use App\Models\Lead;

/**
 * Moves a lead along the pipeline from what happened in its conversation.
 *
 *   first follow-up sent            New → Contacted
 *   reply scored 7–10               → Qualified
 *   reply scored 4–6                → Contacted
 *   reply scored 2–3, or no reply   → Nurturing
 *   reply scored 1 (no / stop)      → Lost
 *
 * Only the early, automatic stages move. Once a person has set Appointment,
 * Proposal, Won, Lost or Archived, nothing here overrides them.
 */
final class ProgressLeadStatus
{
    /** Stages the automation may move a lead out of. */
    private const AUTOMATIC = [LeadStatus::New, LeadStatus::Contacted, LeadStatus::Nurturing, LeadStatus::Qualified];

    public function afterFirstEmail(Lead $lead): void
    {
        if ($lead->status === LeadStatus::New) {
            $this->move($lead, LeadStatus::Contacted);
        }
    }

    public function afterReply(Lead $lead, int $score): void
    {
        $target = match (true) {
            $score >= 7 => LeadStatus::Qualified,
            $score >= 4 => LeadStatus::Contacted,
            $score >= 2 => LeadStatus::Nurturing,
            default => LeadStatus::Lost,
        };

        // A warm lead that later sends a lukewarm line stays qualified.
        if ($lead->status === LeadStatus::Qualified && $target !== LeadStatus::Lost) {
            return;
        }

        $this->move($lead, $target);
    }

    public function afterNoReply(Lead $lead): void
    {
        if (in_array($lead->status, [LeadStatus::New, LeadStatus::Contacted], true)) {
            $this->move($lead, LeadStatus::Nurturing);
        }
    }

    private function move(Lead $lead, LeadStatus $target): void
    {
        if (! in_array($lead->status, self::AUTOMATIC, true) || $lead->status === $target) {
            return;
        }

        $lead->status = $target;

        if ($target === LeadStatus::Qualified && $lead->qualified_at === null) {
            $lead->qualified_at = now();
        }

        $lead->save();
    }
}
