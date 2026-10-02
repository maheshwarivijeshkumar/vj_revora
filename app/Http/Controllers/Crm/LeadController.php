<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Leads\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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

    /** Columns a client may sort by. Anything else is ignored. */
    private const SORTABLE = [
        'full_name', 'email', 'company_name', 'status',
        'score', 'created_at', 'last_activity_at', 'next_follow_up_at',
    ];

    public function index(Request $request): Response
    {
        $perPage = (int) $request->integer('per_page', 25);
        $perPage = in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 25;

        [$sort, $direction] = $this->sort($request);

        $query = Lead::query()
            // Merged duplicates still exist so their history survives, but
            // counting them would double-count the person (§18).
            ->master()
            ->with(['owner:id,name', 'source:id,name,type']);

        $this->applySearch($query, $request->string('search')->trim()->toString());
        $this->applyFilters($query, $request);

        $leads = $query
            ->orderBy($sort, $direction)
            // A stable tiebreak, otherwise rows shuffle between pages when
            // many share the same sort value.
            ->orderByDesc('id')
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
     * Free-text search across the fields someone would actually type (§111).
     *
     * Grouped so the filters that follow cannot be escaped by an OR: without
     * the closure, `status=new` plus a search term would return every lead
     * matching the term regardless of status.
     *
     * @param  Builder<Lead>  $query
     */
    private function applySearch(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query->where(function (Builder $q) use ($like): void {
            $q->where('full_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('company_name', 'like', $like);
        });
    }

    /**
     * @param  Builder<Lead>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        $query
            ->when(
                $this->list($request, 'status'),
                fn (Builder $q, array $values) => $q->whereIn('status', $values),
            )
            ->when(
                $this->list($request, 'owner_id'),
                fn (Builder $q, array $values) => $q->whereIn('owner_id', $values),
            )
            ->when(
                $this->list($request, 'source_id'),
                fn (Builder $q, array $values) => $q->whereIn('lead_source_id', $values),
            )
            ->when(
                $request->filled('score_min'),
                fn (Builder $q) => $q->where('score', '>=', $request->integer('score_min')),
            )
            ->when(
                $request->filled('score_max'),
                fn (Builder $q) => $q->where('score', '<=', $request->integer('score_max')),
            );
    }

    /**
     * A multi-select filter value, normalised to a list.
     *
     * Query strings deliver a single selection as a scalar and several as an
     * array, so both shapes have to be accepted.
     *
     * @return list<string>
     */
    private function list(Request $request, string $key): array
    {
        $value = $request->input($key);

        if ($value === null || $value === '' || $value === []) {
            return [];
        }

        return array_values(array_filter(
            array_map('strval', (array) $value),
            static fn (string $v): bool => $v !== '',
        ));
    }

    /**
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    private function sort(Request $request): array
    {
        $sort = $request->string('sort')->toString();
        $direction = strtolower($request->string('direction')->toString());

        return [
            in_array($sort, self::SORTABLE, true) ? $sort : 'created_at',
            $direction === 'asc' ? 'asc' : 'desc',
        ];
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
            'owner' => $lead->owner?->name,
            'source' => $lead->source?->name,
            // The provenance signal §2 requires on every lead: an imported
            // list and a verified provider submission must not look alike.
            'source_authorized' => $lead->source?->isAuthorizedApi() ?? false,
            'last_activity_at' => $lead->last_activity_at?->toIso8601String(),
            'next_follow_up_at' => $lead->next_follow_up_at?->toIso8601String(),
            'created_at' => $lead->created_at?->toIso8601String(),
        ];
    }
}
