<?php

declare(strict_types=1);

namespace App\Domain\Leads\Actions;

use App\Domain\Leads\DataObjects\CaptureResult;
use App\Domain\Leads\DataObjects\NormalizedLead;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Enums\ScoreMethod;
use App\Domain\Leads\Events\LeadCaptured;
use App\Domain\Leads\Exceptions\UnusableLeadException;
use App\Domain\Leads\Services\LeadAssigner;
use App\Domain\Leads\Services\LeadDeduplicator;
use App\Domain\Leads\Services\LeadScorer;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\LeadMerge;
use App\Models\LeadSource;
use Illuminate\Support\Facades\DB;

/**
 * The lead ingestion pipeline (§5, §85).
 *
 * Every source converges here: provider webhooks, website forms, imports, the
 * public API and manual entry. There is deliberately no per-provider ingestion
 * path, because the interesting behaviour — deduplication, scoring, routing —
 * must be identical however a lead arrived.
 *
 *   normalize → deduplicate → score → assign → emit
 *
 * Normalization happens before this runs; callers pass a NormalizedLead.
 */
final readonly class CaptureLead
{
    public function __construct(
        private LeadDeduplicator $deduplicator,
        private LeadScorer $scorer,
        private LeadAssigner $assigner,
    ) {}

    /**
     * @param  array<string, mixed>  $providerEvent  Raw payload, kept for audit.
     *
     * @throws UnusableLeadException when there is no way to identify the person.
     */
    public function handle(
        NormalizedLead $normalized,
        ?LeadSource $source = null,
        array $providerEvent = [],
        ?string $providerEventId = null,
        bool $assign = true,
    ): CaptureResult {
        if (! $normalized->isUsable()) {
            throw UnusableLeadException::make();
        }

        return DB::transaction(function () use ($normalized, $source, $providerEvent, $providerEventId, $assign): CaptureResult {
            $match = $this->deduplicator->findMaster($normalized);

            $result = $match === null
                ? $this->createLead($normalized, $source)
                : $this->enrichExisting($match['lead'], $normalized, $match['matched_on']);

            $lead = $result['lead'];

            // Everything below saves the lead again while scoring and routing
            // it. Those saves are part of capturing the lead, not edits to it,
            // so anything that reacts to an update is asked to stay quiet until
            // LeadCaptured says the pipeline has finished.
            $lead->isBeingCaptured = true;

            $this->recordEvent($lead, LeadEvent::CAPTURED, [
                'source' => $source?->key,
                'duplicate' => $result['duplicate'],
                'matched_on' => $result['matched_on'],
                'payload' => $providerEvent === [] ? null : $providerEvent,
            ], $source?->provider, $providerEventId);

            // Rescored on every touch: a second submission usually carries the
            // information that changes the answer, which is the whole reason
            // to enrich rather than ignore a duplicate.
            $score = $this->scorer->apply($lead, ScoreMethod::Rules);

            $this->recordEvent($lead, LeadEvent::SCORED, $score->toArray());

            // Only new leads are routed. Reassigning on a repeat submission
            // would pull the lead out from under whoever is already working it.
            $assignment = null;

            if ($assign && ! $result['duplicate'] && $lead->owner_id === null) {
                $assignment = $this->assigner->assign($lead);

                if ($assignment !== null) {
                    $this->recordEvent($lead, LeadEvent::ASSIGNED, [
                        'user_id' => $assignment->user_id,
                        'strategy' => $assignment->strategy,
                        'reason' => $assignment->reason,
                    ]);
                }
            }

            $refreshed = $lead->refresh();
            $refreshed->isBeingCaptured = false;

            $captured = new CaptureResult(
                lead: $refreshed,
                isDuplicate: $result['duplicate'],
                matchedOn: $result['matched_on'],
                score: $score,
                assignedTo: $assignment?->user_id,
                filled: $result['filled'],
            );

            LeadCaptured::dispatch($captured);

            return $captured;
        });
    }

    /**
     * @return array{lead: Lead, duplicate: bool, matched_on: string|null, filled: list<string>}
     */
    private function createLead(NormalizedLead $normalized, ?LeadSource $source): array
    {
        $touch = array_filter([
            'utm' => $normalized->utm === [] ? null : $normalized->utm,
            'landing_page' => $normalized->landingPage,
            'referrer' => $normalized->referrer,
            'source' => $source?->key,
            'at' => now()->toIso8601String(),
        ]);

        $lead = Lead::create([
            ...$normalized->toAttributes(),
            'lead_source_id' => $source?->id,
            'status' => LeadStatus::New,
            // First touch is written once and never updated: that is what
            // makes first-touch attribution meaningful (§34).
            'first_touch' => $touch,
            'last_touch' => $touch,
            'consent_at' => $normalized->consent ? now() : null,
            'last_activity_at' => now(),
        ]);

        return ['lead' => $lead, 'duplicate' => false, 'matched_on' => null, 'filled' => []];
    }

    /**
     * @return array{lead: Lead, duplicate: bool, matched_on: string, filled: list<string>}
     */
    private function enrichExisting(Lead $master, NormalizedLead $normalized, string $matchedOn): array
    {
        // Set before the first save, not after: the enrichment save is the one
        // that would otherwise be announced as an edit in its own right,
        // alongside the single lead.updated this capture is entitled to.
        $master->isBeingCaptured = true;

        $filled = $this->deduplicator->mergeInto($master, $normalized);
        $master->last_activity_at = now();
        $master->save();

        // Recorded even when nothing was filled, because "we saw this person
        // again" is itself signal for scoring and for the timeline.
        LeadMerge::create([
            'master_lead_id' => $master->id,
            'merged_lead_id' => $master->id,
            'matched_on' => $matchedOn,
            'diff' => $filled === [] ? null : $filled,
            'merged_at' => now(),
        ]);

        $this->recordEvent($master, LeadEvent::DUPLICATE_MATCHED, [
            'matched_on' => $matchedOn,
            'filled' => array_keys($filled),
        ]);

        return [
            'lead' => $master,
            'duplicate' => true,
            'matched_on' => $matchedOn,
            // last_activity_at always moves and is deliberately not counted:
            // "we saw them again" is not "we learned something".
            'filled' => array_keys($filled),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function recordEvent(
        Lead $lead,
        string $type,
        array $payload,
        ?string $provider = null,
        ?string $providerEventId = null,
    ): void {
        LeadEvent::create([
            'lead_id' => $lead->id,
            'type' => $type,
            'provider' => $provider,
            'provider_event_id' => $providerEventId,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
