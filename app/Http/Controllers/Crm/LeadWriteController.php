<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Leads\Actions\CaptureLead;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\LeadNormalizer;
use App\Domain\Leads\Services\LeadScorer;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\LeadFormRequest;
use App\Models\Lead;
use App\Models\LeadSource;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;

/**
 * Creating and editing leads from the UI (§44).
 */
final class LeadWriteController extends Controller
{
    /**
     * Fields the form owns that are not part of the normalised lead schema.
     *
     * Held back from the normalizer, which would otherwise file anything it
     * does not recognise as provider metadata (§17) — so `owner_id` would be
     * stored as a metadata string instead of setting the owner.
     *
     * @var list<string>
     */
    private const FORM_ONLY = [
        'status', 'owner_id', 'lead_source_id', 'next_follow_up_at',
    ];

    /**
     * Creates a lead through the same pipeline every other source uses.
     *
     * §5 and §85 list manual entry alongside provider webhooks, forms and
     * imports for a reason: a rep typing in someone who is already on file
     * should enrich that record, not create the workspace's second copy of
     * them. Running the capture pipeline here is what makes deduplication,
     * scoring and routing identical whichever door a lead came through.
     */
    public function store(LeadFormRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $source = isset($validated['lead_source_id'])
            ? LeadSource::query()->whereKey($validated['lead_source_id'])->first()
            : null;

        $ownerId = $validated['owner_id'] ?? null;
        $status = LeadStatus::from((string) $validated['status']);

        $normalized = app(LeadNormalizer::class)->normalize(
            array_diff_key($validated, array_flip(self::FORM_ONLY)),
        );

        $result = app(CaptureLead::class)->handle(
            $normalized,
            $source,
            // Auto-routing is skipped when the form already chose someone, so
            // the assignment engine cannot overrule an explicit decision.
            assign: $ownerId === null,
        );

        $lead = $result->lead;

        $this->applyFormChoices(
            $lead,
            $ownerId,
            $status,
            // Parsed here rather than assigned as a string: the cast would
            // accept it, but every reader of this method would then have to
            // know which it is.
            $request->date('next_follow_up_at'),
        );

        if ($result->isDuplicate) {
            // Said out loud. Silently merging would leave the rep looking for a
            // new row that was never created, and wondering what went wrong.
            return to_route('leads.index')->with(
                'warning',
                "{$lead->displayName()} was already on file, so these details were added to the existing lead.",
            );
        }

        return to_route('leads.index')
            ->with('success', "{$lead->displayName()} has been added.");
    }

    public function update(LeadFormRequest $request, Lead $lead): RedirectResponse
    {
        $validated = $request->validated();

        $lead->fill(array_diff_key($validated, array_flip(self::FORM_ONLY)));
        $lead->owner_id = $validated['owner_id'] ?? null;
        $lead->lead_source_id = $validated['lead_source_id'] ?? null;
        $lead->next_follow_up_at = $request->date('next_follow_up_at');
        $lead->status = LeadStatus::from((string) $validated['status']);

        // Stamped once, the first time the lead qualifies, so a later edit does
        // not reset time-to-qualify reporting.
        if ($lead->status === LeadStatus::Qualified && $lead->qualified_at === null) {
            $lead->qualified_at = now();
        }

        if ($request->boolean('consent') && $lead->consent_at === null) {
            $lead->consent_at = now();
            $lead->consent_source = 'manual-entry';
        }

        $lead->save();

        // Rescored, because an edit that adds a company or a phone number
        // changes the answer and leaving the old score would be a lie (§19).
        app(LeadScorer::class)->apply($lead);

        return back()->with('success', "{$lead->displayName()} has been updated.");
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $name = $lead->displayName();

        // Soft delete: history has to survive, and an accidental delete has to
        // be recoverable (§18).
        $lead->delete();

        return to_route('leads.index')->with('success', "{$name} has been deleted.");
    }

    /**
     * Applies the choices the form made that the pipeline does not own.
     *
     * Only when they differ, so a plain create does not write a second time and
     * produce a lead.updated nobody asked for.
     */
    private function applyFormChoices(
        Lead $lead,
        ?int $ownerId,
        LeadStatus $status,
        ?CarbonInterface $followUpAt,
    ): void {
        if ($ownerId !== null) {
            $lead->owner_id = $ownerId;
        }

        if ($status !== LeadStatus::New) {
            $lead->status = $status;

            if ($status === LeadStatus::Qualified && $lead->qualified_at === null) {
                $lead->qualified_at = now();
            }
        }

        if ($followUpAt !== null) {
            $lead->next_follow_up_at = $followUpAt;
        }

        // The normalizer labels a capture 'form', which is right for a website
        // submission and wrong here: a colleague ticking the box on someone's
        // behalf is a different kind of evidence, and §88 asks us to be able to
        // say which it was.
        if ($lead->consent && $lead->consent_source !== 'manual-entry') {
            $lead->consent_source = 'manual-entry';
        }

        if ($lead->isDirty()) {
            $lead->save();
        }
    }
}
