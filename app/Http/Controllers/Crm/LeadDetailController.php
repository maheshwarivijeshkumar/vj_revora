<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Leads\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\LeadScore;
use App\Models\LeadSource;
use App\Models\Tag;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One lead, in full (§44).
 *
 * The sections §44 asks for that have something behind them today: overview,
 * source and attribution, score explanation, verification findings, timeline,
 * deals and audit. Conversations, emails, WhatsApp, tasks, appointments, notes
 * and documents arrive with the modules that produce them — rather than as
 * empty tabs that imply a feature exists.
 */
final class LeadDetailController extends Controller
{
    /** Enough to show the story without paging a screen nobody scrolls twice. */
    private const TIMELINE_LIMIT = 50;

    public function show(Lead $lead): Response
    {
        $lead->load(['owner', 'source', 'tags', 'mergedInto']);

        return Inertia::render('crm/leads/Show', [
            'lead' => $this->overview($lead),
            'score' => $this->score($lead),
            'verification' => $this->verification($lead),
            'attribution' => $this->attribution($lead),
            'timeline' => $this->timeline($lead),
            'deals' => $this->deals($lead),
            // Deferred: the audit tab is the least-opened of them and the
            // query is the most expensive, so it loads when asked for.
            'audit' => Inertia::defer(fn (): array => $this->audit($lead)),
            'duplicates' => $this->duplicates($lead),
            // The edit drawer reuses the list's form, so it needs the same
            // option lists. Deferred for the same reason it is there.
            'options' => Inertia::defer(fn (): array => [
                'statuses' => LeadStatus::options(),
                'owners' => User::query()
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (User $u): array => ['value' => (string) $u->id, 'label' => $u->name])
                    ->all(),
                'sources' => LeadSource::query()
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (LeadSource $s): array => ['value' => (string) $s->id, 'label' => $s->name])
                    ->all(),
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function overview(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'uuid' => $lead->uuid,
            'name' => $lead->displayName(),
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            // The dialable form, so a click-to-call link cannot send the user
            // to a number that only works from one country.
            'phone_e164' => $lead->phone_normalized,
            'phone_type' => $lead->phone_type,
            'phone_country' => $lead->phone_country,
            'company_name' => $lead->company_name,
            'job_title' => $lead->job_title,
            'website' => $lead->website,
            'country' => $lead->country,
            'status' => $lead->status->value,
            'status_label' => $lead->status->label(),
            'status_tone' => $lead->status->tone(),
            'owner' => $lead->owner?->name,
            'owner_id' => $lead->owner_id,
            'lead_source_id' => $lead->lead_source_id,
            'consent' => $lead->consent,
            'consent_at' => $lead->consent_at?->toIso8601String(),
            'consent_source' => $lead->consent_source,
            'tags' => $lead->tags->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->all(),
            'next_follow_up_at' => $lead->next_follow_up_at?->toIso8601String(),
            'last_activity_at' => $lead->last_activity_at?->toIso8601String(),
            'qualified_at' => $lead->qualified_at?->toIso8601String(),
            'created_at' => $lead->created_at?->toIso8601String(),
            // A merged lead is a tombstone: showing it as an ordinary record
            // would have someone working a person who lives under another id.
            'merged_into' => $lead->mergedInto === null ? null : [
                'id' => $lead->mergedInto->id,
                'name' => $lead->mergedInto->displayName(),
            ],
        ];
    }

    /**
     * The score and the reasons that produced it (§19).
     *
     * @return array<string, mixed>
     */
    private function score(Lead $lead): array
    {
        $latest = LeadScore::query()
            ->where('lead_id', $lead->id)
            ->latest('id')
            ->first();

        return [
            'score' => $lead->score,
            'band' => $lead->score_band->value,
            'band_label' => $lead->score_band->label(),
            'method' => $latest === null ? null : $latest->method->value,
            // §19: a rep who cannot see why a lead scored 82 has no way to
            // trust it, and will end up ignoring the number.
            'reasons' => $latest === null ? [] : $latest->reasons,
            'scored_at' => $latest === null ? null : $latest->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function verification(Lead $lead): array
    {
        return [
            'status' => $lead->verification_status->value,
            'label' => $lead->verification_status->label(),
            'tone' => $lead->verification_status->tone(),
            'confidence' => $lead->verification_confidence,
            'findings' => $lead->verification_findings ?? [],
            'verified_at' => $lead->verified_at?->toIso8601String(),
        ];
    }

    /**
     * Where the lead came from (§2, §34).
     *
     * @return array<string, mixed>
     */
    private function attribution(Lead $lead): array
    {
        return [
            'source' => $lead->source === null ? null : [
                'name' => $lead->source->name,
                'type' => $lead->source->type->value,
                // §2 requires an imported list and a verified provider
                // submission to be distinguishable at a glance.
                'authorized_api' => $lead->source->isAuthorizedApi(),
            ],
            'utm' => $lead->utm ?? [],
            'landing_page' => $lead->landing_page,
            'referrer' => $lead->referrer,
            // First touch is written once and never updated, which is what
            // makes first-touch attribution mean anything (§34).
            'first_touch' => $lead->first_touch ?? [],
            'last_touch' => $lead->last_touch ?? [],
            'external_system' => $lead->external_system,
            'external_record_id' => $lead->external_record_id,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function timeline(Lead $lead): array
    {
        return LeadEvent::query()
            ->where('lead_id', $lead->id)
            ->with('user:id,name')
            // Newest first: a timeline is read from what just happened
            // backwards, and id breaks ties within the same second.
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(self::TIMELINE_LIMIT)
            ->get()
            ->map(fn (LeadEvent $event): array => [
                'id' => $event->id,
                'type' => $event->type,
                'label' => $this->eventLabel($event->type),
                'provider' => $event->provider,
                'user' => $event->user?->name,
                'payload' => $event->payload,
                'occurred_at' => $event->occurred_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function deals(Lead $lead): array
    {
        return Deal::query()
            ->where('lead_id', $lead->id)
            ->with(['stage:id,name', 'pipeline:id,name'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Deal $deal): array => [
                'id' => $deal->id,
                'title' => $deal->title,
                'value' => (float) $deal->value,
                'currency' => $deal->currency,
                'status' => $deal->status->value,
                'stage' => $deal->stage?->name,
                'pipeline' => $deal->pipeline?->name,
            ])
            ->all();
    }

    /**
     * Leads that were merged into this one.
     *
     * Shown because a master record quietly containing three other people's
     * submissions is surprising, and the rep needs to know the history is
     * combined rather than original.
     *
     * @return array<int, array<string, mixed>>
     */
    private function duplicates(Lead $lead): array
    {
        return Lead::query()
            ->where('merged_into_id', $lead->id)
            ->get()
            ->map(fn (Lead $duplicate): array => [
                'id' => $duplicate->id,
                'name' => $duplicate->displayName(),
                'email' => $duplicate->email,
                'created_at' => $duplicate->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function audit(Lead $lead): array
    {
        return AuditLog::query()
            ->where('entity_type', Lead::class)
            ->where('entity_id', $lead->id)
            ->with('actor')
            ->orderByDesc('id')
            ->limit(self::TIMELINE_LIMIT)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'actor' => $this->actorName($log),
                'before' => $log->before,
                'after' => $log->after,
                'ip' => $log->ip,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function actorName(AuditLog $log): string
    {
        $actor = $log->actor;

        if ($actor instanceof User) {
            return $actor->name;
        }

        if ($actor instanceof ApiKey) {
            return $actor->name.' (API key)';
        }

        return 'System';
    }

    /**
     * Turns an event type into something readable.
     *
     * Falls back to the raw type rather than hiding an event nobody has
     * labelled yet: providers may add their own, and a gap in the timeline is
     * worse than an ugly line in it.
     */
    private function eventLabel(string $type): string
    {
        return match ($type) {
            LeadEvent::CAPTURED => 'Captured',
            LeadEvent::SCORED => 'Scored',
            LeadEvent::VERIFIED => 'Details checked',
            LeadEvent::ASSIGNED => 'Assigned',
            LeadEvent::STATUS_CHANGED => 'Status changed',
            LeadEvent::MERGED => 'Merged',
            LeadEvent::DUPLICATE_MATCHED => 'Seen again',
            default => str_replace(['lead.', '_'], ['', ' '], $type),
        };
    }
}
