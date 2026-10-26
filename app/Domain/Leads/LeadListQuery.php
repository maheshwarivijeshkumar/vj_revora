<?php

declare(strict_types=1);

namespace App\Domain\Leads;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The leads list's search, filters and sort, read from a request (§110–115).
 *
 * Shared by the table and the Excel export, so an export always contains
 * exactly the rows the user was looking at.
 */
final class LeadListQuery
{
    /** Columns a client may sort by. Anything else is ignored. */
    public const SORTABLE = [
        'full_name', 'email', 'company_name', 'status',
        'score', 'created_at', 'last_activity_at', 'next_follow_up_at',
        'verification_status', 'verification_confidence', 'engagement_score',
    ];

    /**
     * @return Builder<Lead>
     */
    public function build(Request $request): Builder
    {
        [$sort, $direction] = $this->sort($request);

        $query = Lead::query()
            // Merged duplicates still exist so their history survives, but
            // counting them would double-count the person (§18).
            ->master();

        $this->applySearch($query, $request->string('search')->trim()->toString());
        $this->applyFilters($query, $request);

        return $query
            ->orderBy($sort, $direction)
            // A stable tiebreak, otherwise rows shuffle between pages when
            // many share the same sort value.
            ->orderByDesc('id');
    }

    /**
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    public function sort(Request $request): array
    {
        $sort = $request->string('sort')->toString();
        $direction = strtolower($request->string('direction')->toString());

        return [
            in_array($sort, self::SORTABLE, true) ? $sort : 'created_at',
            $direction === 'asc' ? 'asc' : 'desc',
        ];
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
                $this->list($request, 'verification'),
                fn (Builder $q, array $values) => $q->whereIn('verification_status', $values),
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
}
