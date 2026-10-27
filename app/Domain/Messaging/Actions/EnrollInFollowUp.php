<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Leads\Enums\LeadSourceType;
use App\Domain\Messaging\Enums\EnrollmentStatus;
use App\Models\EmailAccount;
use App\Models\FollowUpEnrollment;
use App\Models\Lead;

/**
 * Starts the follow-up sequence for a lead.
 *
 * Only for people who asked to be contacted: leads from ad forms, website
 * forms and the API. An imported list did not ask, and emailing it on a timer
 * is how a domain ends up on a blocklist (§88).
 */
final readonly class EnrollInFollowUp
{
    public function __construct(
        private EnsureDefaultFollowUp $defaults,
    ) {}

    public function handle(Lead $lead): ?FollowUpEnrollment
    {
        if (blank($lead->email) || $lead->email_opted_out_at !== null) {
            return null;
        }

        if ($lead->source?->type === LeadSourceType::Import) {
            return null;
        }

        $sender = EmailAccount::sender();

        if ($sender === null) {
            return null;
        }

        $sequence = $this->defaults->handle();

        if (! $sequence->is_active) {
            return null;
        }

        $firstDelay = (int) ($sequence->steps->first()->delay_minutes ?? 0);

        return FollowUpEnrollment::query()->firstOrCreate(
            ['lead_id' => $lead->id, 'follow_up_sequence_id' => $sequence->id],
            [
                'email_account_id' => $sender->id,
                'status' => EnrollmentStatus::Active,
                'next_position' => 1,
                'started_at' => now(),
                'next_run_at' => now()->addMinutes($firstDelay),
            ],
        );
    }
}
