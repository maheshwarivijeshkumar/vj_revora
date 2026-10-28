<?php

declare(strict_types=1);

namespace App\Domain\Opportunities\Actions;

use App\Domain\Leads\Actions\CaptureLead;
use App\Domain\Leads\DataObjects\NormalizedLead;
use App\Domain\Leads\Enums\LeadSourceType;
use App\Models\JobPost;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\OpportunityMatch;
use App\Models\Tender;

/**
 * Turns an opportunity worth pursuing into a lead in the pipeline.
 *
 * Through the normal capture pipeline, so it is deduplicated and scored like
 * any other lead. Recorded as entered by a person: nobody at the company
 * asked to be contacted, so it gets no automatic follow-up emails (§2, §88).
 */
final readonly class ConvertOpportunityToLead
{
    public function __construct(
        private CaptureLead $capture,
    ) {}

    public function handle(OpportunityMatch $match): Lead
    {
        if ($match->lead_id !== null && ($existing = Lead::query()->find($match->lead_id)) !== null) {
            return $existing;
        }

        $opportunity = $match->opportunity();

        $source = LeadSource::query()->firstOrCreate(
            ['key' => 'opportunities'],
            ['name' => 'Opportunities (tenders & job posts)', 'type' => LeadSourceType::Manual],
        );

        $lead = $this->capture->handle(
            new NormalizedLead(
                fullName: $this->contactName($opportunity),
                companyName: $this->company($opportunity),
                website: $opportunity?->url,
                externalSystem: 'opportunity',
                externalRecordType: $match->opportunity_type,
                externalRecordId: $match->uuid,
                metadata: array_filter([
                    'opportunity_title' => $opportunity?->title,
                    'opportunity_source' => $opportunity?->sourceLabel(),
                    'opportunity_reference' => $opportunity instanceof Tender ? $opportunity->reference : null,
                    'opportunity_fit' => $match->fit_score,
                    'service_profile' => $match->profile?->name,
                ]),
            ),
            $source,
            assign: true,
        )->lead;

        $match->forceFill(['lead_id' => $lead->id])->save();

        return $lead;
    }

    private function company(Tender|JobPost|null $opportunity): ?string
    {
        return match (true) {
            $opportunity instanceof Tender => $opportunity->department ?: $opportunity->organization,
            $opportunity instanceof JobPost => $opportunity->company ?: ($opportunity->source === 'upwork' ? 'Upwork client' : null),
            default => null,
        };
    }

    /** A readable name for the record: there is no person yet. */
    private function contactName(Tender|JobPost|null $opportunity): ?string
    {
        return $opportunity === null ? null : mb_substr($opportunity->title, 0, 120);
    }
}
