<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Activity\TimelinePresenter;
use App\Domain\Documents\DocumentPresenter;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealStageChange;
use App\Models\Tag;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One deal, in full (§22, §44).
 *
 * The sections with something behind them today: overview, the people and
 * company it is with, what people did against it, its files, stage history with
 * time spent in each, and audit. Quotes and appointments arrive with the modules
 * that produce them, rather than as empty tabs implying a feature exists
 * (§124).
 */
final class DealDetailController extends Controller
{
    private const AUDIT_LIMIT = 50;

    public function show(Deal $deal): Response
    {
        $deal->load(['pipeline', 'stage', 'owner', 'company', 'contact', 'lead', 'tags']);

        return Inertia::render('crm/deals/Show', [
            'deal' => $this->overview($deal),
            'stageHistory' => $this->stageHistory($deal),
            'related' => $this->related($deal),
            'timelinePanel' => app(TimelinePresenter::class)->forSubject($deal),
            'documents' => app(DocumentPresenter::class)->forSubject($deal),
            // Deferred: the least-opened tab and the most expensive query.
            'audit' => Inertia::defer(fn (): array => $this->audit($deal)),
            'timelineOptions' => Inertia::defer(
                fn (): array => app(TimelinePresenter::class)->options(),
            ),
            'documentConstraints' => Inertia::defer(
                fn (): array => app(DocumentPresenter::class)->constraints(),
            ),
            'options' => Inertia::defer(fn (): array => $this->formOptions()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function overview(Deal $deal): array
    {
        return [
            'id' => $deal->id,
            'uuid' => $deal->uuid,
            'title' => $deal->title,
            'value' => (float) $deal->value,
            'currency' => $deal->currency,
            'probability' => $deal->probability,
            // The number a forecast is built from, so every reader does not
            // recompute it and disagree about the rounding.
            'weighted_value' => $deal->weightedValue(),
            'status' => $deal->status->value,
            'status_label' => $deal->status->label(),
            'status_tone' => $deal->status->tone(),
            'is_open' => $deal->isOpen(),
            'pipeline' => $deal->pipeline?->name,
            'pipeline_id' => $deal->pipeline_id,
            'stage' => $deal->stage?->name,
            'stage_id' => $deal->pipeline_stage_id,
            'owner' => $deal->owner?->name,
            'owner_id' => $deal->owner_id,
            'company_id' => $deal->company_id,
            'contact_id' => $deal->contact_id,
            'expected_close_date' => $deal->expected_close_date?->toDateString(),
            'closed_at' => $deal->closed_at?->toIso8601String(),
            'lost_reason' => $deal->lost_reason,
            'tags' => $deal->tags->map(fn (Tag $tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])->all(),
            'created_at' => $deal->created_at?->toIso8601String(),
        ];
    }

    /**
     * How the deal moved, and how long it sat in each stage (§22).
     *
     * `seconds_in_previous_stage` is recorded on the move rather than derived
     * from timestamps here, so a stage renamed or deleted later does not
     * rewrite history.
     *
     * @return array<int, array<string, mixed>>
     */
    private function stageHistory(Deal $deal): array
    {
        return DealStageChange::query()
            ->where('deal_id', $deal->id)
            ->with(['fromStage:id,name', 'toStage:id,name'])
            // Oldest first: this is the one list on the page that reads as a
            // story rather than as a feed.
            ->orderBy('changed_at')
            ->orderBy('id')
            ->get()
            ->map(fn (DealStageChange $change): array => [
                'id' => $change->id,
                'from' => $change->fromStage?->name,
                'to' => $change->toStage?->name,
                'seconds_in_previous' => $change->seconds_in_previous_stage,
                'changed_at' => $change->changed_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * The people and company this deal is with (§21).
     *
     * @return array<string, mixed>
     */
    private function related(Deal $deal): array
    {
        return [
            'company' => $deal->company === null ? null : [
                'id' => $deal->company->id,
                'name' => $deal->company->name,
                'domain' => $deal->company->domain,
            ],
            'contact' => $deal->contact === null ? null : [
                'id' => $deal->contact->id,
                'name' => $deal->contact->displayName(),
                'email' => $deal->contact->email,
                'phone' => $deal->contact->phone,
            ],
            // Where the deal came from, so the acquisition story survives the
            // conversion from lead to opportunity (§85).
            'lead' => $deal->lead === null ? null : [
                'id' => $deal->lead->id,
                'name' => $deal->lead->displayName(),
                'score' => $deal->lead->score,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function audit(Deal $deal): array
    {
        return AuditLog::query()
            ->where('entity_type', Deal::class)
            ->where('entity_id', $deal->id)
            ->with('actor')
            ->orderByDesc('id')
            ->limit(self::AUDIT_LIMIT)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'actor' => $this->actorName($log),
                'before' => $log->before,
                'after' => $log->after,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Options for the edit drawer, which is the same component the board uses.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'owners' => User::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $u): array => ['value' => (string) $u->id, 'label' => $u->name])
                ->all(),
            'companies' => Company::query()
                ->orderBy('name')
                ->limit(200)
                ->get(['id', 'name'])
                ->map(fn (Company $c): array => ['value' => (string) $c->id, 'label' => $c->name])
                ->all(),
            'contacts' => Contact::query()
                ->orderBy('full_name')
                ->limit(200)
                ->get(['id', 'full_name', 'email'])
                ->map(fn (Contact $c): array => [
                    'value' => (string) $c->id,
                    'label' => $c->displayName(),
                    'note' => $c->email,
                ])
                ->all(),
            'currencies' => Deal::query()
                ->distinct()
                ->orderBy('currency')
                ->pluck('currency')
                ->map(fn (string $code): array => ['value' => $code, 'label' => $code])
                ->all(),
        ];
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
}
