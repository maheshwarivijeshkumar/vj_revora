<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Enums\VerificationStatus;
use App\Domain\Leads\LeadListQuery;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The leads list (§70, §110–115).
 *
 * Search, filtering, sorting and pagination all happen in the database.
 * §72 and §112 are explicit that thousands of rows must never be shipped to
 * the browser just so it can paginate them.
 */
final class LeadController extends Controller
{
    /** Page sizes offered in the footer (§112). */
    private const PAGE_SIZES = [10, 25, 50, 100, 250];

    public function __construct(
        private readonly LeadListQuery $listQuery,
    ) {}

    public function index(Request $request): Response
    {
        $perPage = (int) $request->integer('per_page', 25);
        $perPage = in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 25;

        [$sort, $direction] = $this->listQuery->sort($request);

        $query = $this->listQuery->build($request)
            ->with(['owner:id,name', 'source:id,name,type']);

        $leads = $query
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Lead $lead): array => $this->row($lead));

        return Inertia::render('crm/leads/Index', [
            'leads' => $leads,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'status' => $request->input('status', []),
                'owner_id' => $request->input('owner_id', []),
                'source_id' => $request->input('source_id', []),
                'verification' => $request->input('verification', []),
                'score_min' => $request->input('score_min'),
                'score_max' => $request->input('score_max'),
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            // Deferred: the filter panel is closed on first paint, so its
            // options need not block the table rendering (Inertia v3).
            'options' => Inertia::defer(fn (): array => [
                'statuses' => LeadStatus::options(),
                'verificationStatuses' => VerificationStatus::options(),
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
            'pageSizes' => self::PAGE_SIZES,
        ]);
    }

    /**
     * One table row.
     *
     * Shaped here rather than sent as a full model, so the browser receives
     * only what the table draws.
     *
     * @return array<string, mixed>
     */
    private function row(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'uuid' => $lead->uuid,
            'name' => $lead->displayName(),
            'email' => $lead->email,
            'phone' => $lead->phone,
            'company' => $lead->company_name,
            'status' => $lead->status->value,
            'status_label' => $lead->status->label(),
            'status_tone' => $lead->status->tone(),
            'score' => $lead->score,
            // Out of 10, from follow-up replies; 0 means they never answered.
            'engagement_score' => $lead->engagement_score,
            'engagement_reason' => $lead->engagement_reason,
            'verification' => $lead->verification_status->value,
            'verification_label' => $lead->verification_status->label(),
            'verification_tone' => $lead->verification_status->tone(),
            'verification_confidence' => $lead->verification_confidence,
            // The reasons travel with the row, so hovering a flag explains it
            // rather than costing a request (§59).
            'verification_problems' => array_column(
                array_filter(
                    $lead->verification_findings ?? [],
                    fn (array $finding): bool => $finding['verdict'] !== 'pass',
                ),
                'detail',
            ),
            'owner' => $lead->owner?->name,
            'source' => $lead->source?->name,
            // The provenance signal §2 requires on every lead: an imported
            // list and a verified provider submission must not look alike.
            'source_authorized' => $lead->source?->isAuthorizedApi() ?? false,
            'last_activity_at' => $lead->last_activity_at?->toIso8601String(),
            'next_follow_up_at' => $lead->next_follow_up_at?->toIso8601String(),
            'created_at' => $lead->created_at?->toIso8601String(),

            // The fields the edit form needs. Carried on the row so opening the
            // drawer is instant: a second request to fetch one record the table
            // already has would be a spinner for nothing.
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'job_title' => $lead->job_title,
            'website' => $lead->website,
            'country' => $lead->country,
            'owner_id' => $lead->owner_id,
            'lead_source_id' => $lead->lead_source_id,
            'consent' => $lead->consent,
        ];
    }
}
