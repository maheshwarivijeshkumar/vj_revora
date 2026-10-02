<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Leads\Actions\CaptureLead;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\LeadNormalizer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLeadRequest;
use App\Http\Requests\Api\V1\UpdateLeadRequest;
use App\Http\Resources\Api\V1\LeadResource;
use App\Models\Lead;
use App\Models\LeadSource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public lead endpoints (§47).
 *
 * These call the same domain actions the internal UI uses, so the public API
 * cannot quietly drift from what the application itself can do (ADR-001).
 */
final class LeadController extends Controller
{
    private const MAX_PER_PAGE = 100;

    public function index(Request $request): AnonymousResourceCollection
    {
        // Validated rather than parsed defensively: an unparseable date is a
        // caller mistake worth a 422, not a filter to quietly ignore, and
        // silently returning everything would look like the filter worked.
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes'],
            'email' => ['sometimes', 'string', 'max:255'],
            'updated_since' => ['sometimes', 'date'],
        ]);

        $perPage = min($request->integer('per_page', 25) ?: 25, self::MAX_PER_PAGE);

        $leads = Lead::query()
            ->master()
            ->with(['owner', 'source'])
            ->when(
                $request->filled('status'),
                fn (Builder $q) => $q->whereIn('status', (array) $request->input('status')),
            )
            ->when(
                $request->filled('email'),
                fn (Builder $q) => $q->where(
                    'email_normalized',
                    mb_strtolower(trim((string) $request->input('email'))),
                ),
            )
            ->when(
                $request->filled('updated_since'),
                fn (Builder $q) => $q->where('updated_at', '>=', $request->date('updated_since')),
            )
            // Ascending by id so a caller paging through the whole list does
            // not see rows shuffle as new leads arrive mid-crawl.
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return LeadResource::collection($leads);
    }

    public function show(string $uuid): LeadResource
    {
        return new LeadResource($this->find($uuid)->load(['owner', 'source']));
    }

    /**
     * Creates a lead through the full capture pipeline.
     *
     * Deduplication applies here exactly as it does to a provider webhook, so
     * posting the same person twice enriches one record rather than creating
     * two. A caller that wanted a duplicate can tell from `is_duplicate`.
     */
    public function store(StoreLeadRequest $request): JsonResponse
    {
        $source = $request->filled('source')
            ? LeadSource::query()->where('key', $request->string('source'))->first()
            : LeadSource::query()->where('key', 'api')->first();

        // The whole payload, not just validated(), which drops anything not
        // named in the rules. §17 requires provider-specific fields to be
        // preserved as metadata rather than discarded, and a partner sending
        // `budget` or `how_did_you_hear` must not lose those answers. The
        // rules still constrain every field that reaches a core column, and
        // the normalizer keeps only scalars for the rest.
        $normalized = app(LeadNormalizer::class)->normalize($request->all());

        $result = app(CaptureLead::class)->handle($normalized, $source);

        return (new LeadResource($result->lead->load(['owner', 'source'])))
            ->additional([
                'meta' => [
                    'is_duplicate' => $result->isDuplicate,
                    'matched_on' => $result->matchedOn,
                    'score' => $result->score->toArray(),
                ],
            ])
            ->response()
            // 200 rather than 201 for a duplicate: nothing was created, and
            // saying otherwise would make a retrying client think it had made
            // a second lead.
            ->setStatusCode($result->isDuplicate ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    public function update(UpdateLeadRequest $request, string $uuid): LeadResource
    {
        $lead = $this->find($uuid);

        $lead->fill($request->validated());

        if ($request->filled('status')) {
            $status = LeadStatus::from($request->string('status')->toString());
            $lead->status = $status;

            // Stamped once, the first time the lead qualifies, so
            // time-to-qualify reporting is not reset by a later edit.
            if ($status === LeadStatus::Qualified && $lead->qualified_at === null) {
                $lead->qualified_at = now();
            }
        }

        $lead->save();

        return new LeadResource($lead->load(['owner', 'source']));
    }

    public function destroy(string $uuid): JsonResponse
    {
        // Soft delete: §18 forbids silently destroying data, and an
        // integration bug must not be able to erase a workspace's history.
        $this->find($uuid)->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Resolves a lead by its public uuid.
     *
     * The tenant scope means a uuid from another workspace simply does not
     * resolve, so there is no separate ownership check to forget.
     */
    private function find(string $uuid): Lead
    {
        return Lead::query()->where('uuid', $uuid)->firstOrFail();
    }
}
