<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Crm\Actions\UpsertCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\CompanyFormRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;

/**
 * Creating and editing companies from the UI (§21, §44).
 */
final class CompanyWriteController extends Controller
{
    /**
     * Creates a company, or enriches the one already on file.
     *
     * Matched on domain first and exact name second, the same as the API and
     * the contact form: two Acmes on the board is a data problem no reporting
     * can recover from (§18).
     */
    public function store(CompanyFormRequest $request): RedirectResponse
    {
        $company = app(UpsertCompany::class)->handle($request->validated());

        if (! $company->wasRecentlyCreated) {
            return to_route('companies.index')->with(
                'warning',
                "{$company->name} was already on file, so these details were added to the existing company.",
            );
        }

        return to_route('companies.index')
            ->with('success', "{$company->name} has been added.");
    }

    public function update(CompanyFormRequest $request, Company $company): RedirectResponse
    {
        // Assigned rather than upserted: an edit is about this record, and
        // routing it through the matcher could silently retarget it at another.
        $validated = $request->validated();

        $company->fill($validated);
        // Assigned explicitly, because fill() leaves a key the form omitted
        // untouched and "nobody" has to be a thing the form can say.
        $company->owner_id = $validated['owner_id'] ?? null;
        $company->save();

        return back()->with('success', "{$company->name} has been updated.");
    }

    public function destroy(Company $company): RedirectResponse
    {
        $name = $company->name;

        $company->delete();

        return to_route('companies.index')->with('success', "{$name} has been deleted.");
    }
}
