<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Crm\Actions\UpsertCompany;
use App\Domain\Crm\Actions\UpsertContact;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\ContactFormRequest;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Http\RedirectResponse;

/**
 * Creating and editing contacts from the UI (§21, §44).
 */
final class ContactWriteController extends Controller
{
    /**
     * Fields that describe the company relationship rather than the person.
     *
     * @var list<string>
     */
    private const COMPANY_FIELDS = ['company_id', 'company_name', 'company_role'];

    /**
     * Creates a contact through the same upsert the API uses.
     *
     * A rep adding someone already on file enriches that record instead of
     * creating the workspace's second copy of them (§18) — the same reason the
     * lead form runs the capture pipeline rather than inserting directly.
     */
    public function store(ContactFormRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $company = $this->resolveCompany($validated);

        $contact = app(UpsertContact::class)->handle(
            array_diff_key($validated, array_flip(self::COMPANY_FIELDS)),
            $company,
        );

        if ($company instanceof Company && isset($validated['company_role'])) {
            // Written after the attach, because the upsert decides whether this
            // is the person's primary employer and that choice is not ours.
            $contact->companies()->updateExistingPivot($company->id, [
                'role' => $validated['company_role'],
            ]);
        }

        // Set by Eloquent on insert only, so it answers exactly "was anything
        // new created here".
        if (! $contact->wasRecentlyCreated) {
            return to_route('contacts.index')->with(
                'warning',
                "{$contact->displayName()} was already on file, so these details were added to the existing contact.",
            );
        }

        return to_route('contacts.index')
            ->with('success', "{$contact->displayName()} has been added.");
    }

    public function update(ContactFormRequest $request, Contact $contact): RedirectResponse
    {
        $validated = $request->validated();

        $contact->fill(array_diff_key($validated, array_flip(self::COMPANY_FIELDS)));
        $contact->owner_id = $validated['owner_id'] ?? null;
        $contact->save();

        $company = $this->resolveCompany($validated);

        if ($company instanceof Company) {
            // Primary only when they have no primary yet: someone who has moved
            // on keeps their history, and an edit must not silently reshuffle
            // which employer is considered current (§21).
            $contact->companies()->syncWithoutDetaching([
                $company->id => [
                    'role' => $validated['company_role'] ?? null,
                    'is_primary' => ! $contact->companies()
                        ->wherePivot('is_primary', true)
                        ->exists(),
                ],
            ]);
        }

        return back()->with('success', "{$contact->displayName()} has been updated.");
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $name = $contact->displayName();

        $contact->delete();

        return to_route('contacts.index')->with('success', "{$name} has been deleted.");
    }

    /**
     * The company this submission points at, creating it if it was typed.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveCompany(array $validated): ?Company
    {
        if (isset($validated['company_id'])) {
            // Scoped, so an id from another workspace does not resolve.
            return Company::query()->whereKey($validated['company_id'])->first();
        }

        if (! isset($validated['company_name'])) {
            return null;
        }

        // Matched on name first so two reps typing "Acme" on the same afternoon
        // do not leave two Acmes on the board (§18).
        return app(UpsertCompany::class)
            ->handle(['name' => $validated['company_name']]);
    }
}
