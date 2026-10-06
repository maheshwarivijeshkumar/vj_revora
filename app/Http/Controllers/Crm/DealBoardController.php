<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Deals\Actions\MoveDealToStage;
use App\Domain\Deals\Exceptions\StageTransitionException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The deal Kanban board (§22).
 */
final class DealBoardController extends Controller
{
    /**
     * Cards loaded per column on first paint.
     *
     * A busy early stage can hold hundreds of deals, and rendering all of them
     * would make the board slow to open for no benefit — nobody scrolls to the
     * four hundredth card. The column header always shows the true total.
     */
    /**
     * Offered alongside whatever the workspace already uses.
     *
     * @var list<string>
     */
    private const COMMON_CURRENCIES = ['AED', 'AUD', 'CAD', 'EUR', 'GBP', 'INR', 'SAR', 'USD'];

    private const CARDS_PER_STAGE = 50;

    public function index(Request $request): Response
    {
        $pipeline = $this->resolvePipeline($request);

        return Inertia::render('crm/deals/Board', [
            'pipeline' => [
                'id' => $pipeline->id,
                'name' => $pipeline->name,
            ],
            'pipelines' => Pipeline::query()
                ->where('entity_type', 'deal')
                ->orderBy('sort_order')
                ->get(['id', 'name'])
                ->map(fn (Pipeline $p): array => [
                    'value' => (string) $p->id,
                    'label' => $p->name,
                ])
                ->all(),
            'stages' => $this->stages($pipeline),
            'cardLimit' => self::CARDS_PER_STAGE,
            // Deferred: the board draws without them, and only the form needs
            // them (Inertia v3).
            'options' => Inertia::defer(fn (): array => $this->formOptions()),
        ]);
    }

    /**
     * Moves a card, which is what a drop on the board posts.
     *
     * Returns a redirect rather than JSON so Inertia refreshes the board from
     * the server. The client updates optimistically first; this is what makes
     * the optimistic state true, or corrects it.
     */
    public function move(Request $request, Deal $deal): RedirectResponse
    {
        $validated = $request->validate([
            'stage_id' => ['required', 'integer'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        // Found through the tenant-scoped model, so a stage id from another
        // workspace simply does not resolve.
        // whereKey()->firstOrFail() rather than findOrFail(), which is typed
        // as returning a model *or* a collection. Scoped to the tenant, so a
        // stage id from another workspace simply does not resolve.
        $stage = PipelineStage::query()
            ->whereKey($validated['stage_id'])
            ->firstOrFail();

        try {
            app(MoveDealToStage::class)->handle(
                $deal,
                $stage,
                $validated['position'] ?? null,
                $request->user()?->id,
            );
        } catch (StageTransitionException $e) {
            // Surfaced as a validation error so the board can point at the
            // offending fields rather than showing a generic failure (§59).
            throw ValidationException::withMessages([
                'stage_id' => $e->getMessage(),
                ...($e->missingFields === [] ? [] : ['missing_fields' => $e->missingFields]),
            ]);
        }

        return back();
    }

    private function resolvePipeline(Request $request): Pipeline
    {
        $query = Pipeline::query()->where('entity_type', 'deal');

        if ($request->filled('pipeline')) {
            $pipeline = (clone $query)->find($request->integer('pipeline'));

            if ($pipeline instanceof Pipeline) {
                return $pipeline;
            }
        }

        $pipeline = $query
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first();

        abort_if($pipeline === null, 404, 'This workspace has no deal pipeline yet.');

        return $pipeline;
    }

    /**
     * Columns with their cards and true totals.
     *
     * @return list<array<string, mixed>>
     */
    private function stages(Pipeline $pipeline): array
    {
        $stages = $pipeline->stages()->get();

        // One grouped count for the whole board rather than a count per
        // column, which would be a query per stage on every load.
        $totals = Deal::query()
            ->whereIn('pipeline_stage_id', $stages->pluck('id'))
            ->selectRaw('pipeline_stage_id, COUNT(*) as total, SUM(value) as value')
            ->groupBy('pipeline_stage_id')
            ->get()
            ->keyBy('pipeline_stage_id');

        $columns = [];

        foreach ($stages as $stage) {
            $cards = Deal::query()
                ->where('pipeline_stage_id', $stage->id)
                ->with(['owner:id,name', 'company:id,name'])
                ->boardOrder()
                ->limit(self::CARDS_PER_STAGE)
                ->get();

            $summary = $totals->get($stage->id);

            $columns[] = [
                'id' => $stage->id,
                'name' => $stage->name,
                'key' => $stage->key,
                'probability' => $stage->probability,
                'is_won' => $stage->is_won,
                'is_lost' => $stage->is_lost,
                'required_fields' => $stage->requiredFields(),
                'total' => (int) ($summary->total ?? 0),
                'value' => (float) ($summary->value ?? 0),
                'cards' => $cards->map(fn (Deal $deal): array => [
                    'id' => $deal->id,
                    'uuid' => $deal->uuid,
                    'title' => $deal->title,
                    'value' => (float) $deal->value,
                    'currency' => $deal->currency,
                    'probability' => $deal->probability,
                    'status' => $deal->status->value,
                    'owner' => $deal->owner?->name,
                    'company' => $deal->company?->name,
                    'expected_close_date' => $deal->expected_close_date?->toDateString(),

                    // The fields the edit form needs, so opening it costs
                    // nothing beyond the board request already made.
                    'owner_id' => $deal->owner_id,
                    'company_id' => $deal->company_id,
                    'contact_id' => $deal->contact_id,
                    'lost_reason' => $deal->lost_reason,
                    'stage_name' => $stage->name,
                ])->all(),
            ];
        }

        return $columns;
    }

    /**
     * Options for the create and edit form.
     *
     * Currencies come from what the workspace already uses plus a short default
     * list: a full ISO 4217 dropdown is 180 entries nobody scrolls, and a
     * free-text box produces "usd", "USD " and "Dollars" in one afternoon.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        $used = Deal::query()
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency')
            ->all();

        $currencies = array_values(array_unique([...$used, ...self::COMMON_CURRENCIES]));
        sort($currencies);

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
            'currencies' => array_map(
                fn (string $code): array => ['value' => $code, 'label' => $code],
                $currencies,
            ),
        ];
    }
}
