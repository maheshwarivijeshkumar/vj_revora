<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Deals\Actions\CreateDeal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\DealFormRequest;
use App\Models\Deal;
use App\Models\Pipeline;
use Illuminate\Http\RedirectResponse;

/**
 * Creating and editing deals from the UI (§22, §44).
 */
final class DealWriteController extends Controller
{
    /**
     * Opens a deal through the same action the API uses.
     *
     * CreateDeal places it at the top of the first stage, takes the stage's
     * probability and writes the opening stage-change record, so duration
     * reporting starts from a real entry rather than inferring one from
     * created_at.
     */
    public function store(DealFormRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Scoped, so a pipeline from another workspace does not resolve.
        $pipeline = Pipeline::query()->whereKey($validated['pipeline_id'])->firstOrFail();

        unset($validated['pipeline_id']);

        $deal = app(CreateDeal::class)->handle($validated, $pipeline);

        return to_route('deals.index', ['pipeline' => $pipeline->id])
            ->with('success', "“{$deal->title}” has been opened.");
    }

    /**
     * Edits the fields that are not the stage.
     *
     * The stage is deliberately not editable here: moving a deal is an action
     * with consequences, and the board is where it happens.
     */
    public function update(DealFormRequest $request, Deal $deal): RedirectResponse
    {
        $validated = $request->validated();

        // Changing the pipeline would orphan the deal on a stage that belongs
        // to the old one, so it is fixed once the deal exists.
        unset($validated['pipeline_id']);

        $deal->fill($validated);
        $deal->owner_id = $validated['owner_id'] ?? null;
        $deal->company_id = $validated['company_id'] ?? null;
        $deal->contact_id = $validated['contact_id'] ?? null;
        $deal->save();

        return back()->with('success', "“{$deal->title}” has been updated.");
    }

    public function destroy(Deal $deal): RedirectResponse
    {
        $title = $deal->title;

        $deal->delete();

        return to_route('deals.index')->with('success', "“{$title}” has been deleted.");
    }
}
