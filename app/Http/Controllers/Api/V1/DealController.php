<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Deals\Actions\CreateDeal;
use App\Domain\Deals\Actions\MoveDealToStage;
use App\Domain\Deals\Exceptions\StageTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DealResource;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public deal endpoints (§47).
 */
final class DealController extends Controller
{
    private const MAX_PER_PAGE = 100;

    private const RELATIONS = ['pipeline', 'stage', 'owner', 'company'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 25) ?: 25, self::MAX_PER_PAGE);

        $deals = Deal::query()
            ->with(self::RELATIONS)
            ->when(
                $request->filled('status'),
                fn (Builder $q) => $q->whereIn('status', (array) $request->input('status')),
            )
            ->when(
                $request->filled('pipeline_id'),
                fn (Builder $q) => $q->where('pipeline_id', $request->integer('pipeline_id')),
            )
            ->when(
                $request->filled('updated_since'),
                fn (Builder $q) => $q->where('updated_at', '>=', $request->date('updated_since')),
            )
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return DealResource::collection($deals);
    }

    public function show(string $uuid): DealResource
    {
        return new DealResource($this->find($uuid)->load(self::RELATIONS));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'pipeline_id' => ['nullable', 'integer'],
            'stage_id' => ['nullable', 'integer'],
            'expected_close_date' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
        ]);

        // Resolved through tenant-scoped models, so an id belonging to another
        // workspace simply does not exist here.
        $pipeline = isset($validated['pipeline_id'])
            ? Pipeline::query()->whereKey($validated['pipeline_id'])->firstOrFail()
            : null;

        $stage = isset($validated['stage_id'])
            ? PipelineStage::query()->whereKey($validated['stage_id'])->firstOrFail()
            : null;

        // Pipeline and stage are passed as objects, so their ids must not also
        // reach the attribute array.
        unset($validated['pipeline_id'], $validated['stage_id']);

        $deal = app(CreateDeal::class)->handle($validated, $pipeline, $stage);

        return (new DealResource($deal->load(self::RELATIONS)))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Moves a deal between stages.
     *
     * A separate endpoint rather than a PATCH of `stage_id`, because a move
     * is not a field assignment: it recalculates probability, may close or
     * reopen the deal, reorders two columns and writes a stage-change record.
     * Hiding that behind a generic update would invite callers to bypass it.
     */
    public function move(Request $request, string $uuid): DealResource|JsonResponse
    {
        $validated = $request->validate([
            'stage_id' => ['required', 'integer'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $deal = $this->find($uuid);
        $stage = PipelineStage::query()->whereKey($validated['stage_id'])->firstOrFail();

        try {
            $moved = app(MoveDealToStage::class)->handle(
                $deal,
                $stage,
                $validated['position'] ?? null,
            );
        } catch (StageTransitionException $e) {
            // 422 rather than 400: the request was well-formed, the workspace's
            // own stage rules refused it (§22).
            return response()->json([
                'message' => $e->getMessage(),
                'missing_fields' => $e->missingFields,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new DealResource($moved->load(self::RELATIONS));
    }

    public function update(Request $request, string $uuid): DealResource
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'value' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            // Bounded because it feeds the weighted forecast, and a value
            // outside 0–100 would quietly corrupt it.
            'probability' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['sometimes', 'nullable', 'date'],
            'lost_reason' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            // Stage changes go through move(), which applies the rules.
            'stage_id' => ['prohibited'],
        ], [
            'stage_id.prohibited' => 'Use POST /v1/deals/{id}/move to change the stage.',
        ]);

        $deal = $this->find($uuid);
        $deal->fill($validated)->save();

        return new DealResource($deal->load(self::RELATIONS));
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->find($uuid)->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /** Pipelines and their stages, so a caller can address them by id. */
    public function pipelines(): JsonResponse
    {
        $pipelines = Pipeline::query()
            ->with('stages')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Pipeline $pipeline): array => [
                'id' => $pipeline->id,
                'name' => $pipeline->name,
                'entity_type' => $pipeline->entity_type->value,
                'is_default' => $pipeline->is_default,
                'stages' => $pipeline->stages->map(fn (PipelineStage $stage): array => [
                    'id' => $stage->id,
                    'key' => $stage->key,
                    'name' => $stage->name,
                    'probability' => $stage->probability,
                    'is_won' => $stage->is_won,
                    'is_lost' => $stage->is_lost,
                    'required_fields' => $stage->requiredFields(),
                ])->all(),
            ]);

        return response()->json(['data' => $pipelines]);
    }

    private function find(string $uuid): Deal
    {
        return Deal::query()->where('uuid', $uuid)->firstOrFail();
    }
}
