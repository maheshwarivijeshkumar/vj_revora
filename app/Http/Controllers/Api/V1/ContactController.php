<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Crm\Actions\UpsertContact;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreContactRequest;
use App\Http\Requests\Api\V1\UpdateContactRequest;
use App\Http\Resources\Api\V1\ContactResource;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public contact endpoints (§47).
 *
 * Writes go through the same upsert the rest of the application uses, so an
 * integration gets the same identifier matching as a form fill rather than a
 * parallel path that can drift (ADR-001).
 */
final class ContactController extends Controller
{
    private const MAX_PER_PAGE = 100;

    private const RELATIONS = ['owner', 'companies'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'email' => ['sometimes', 'string', 'max:255'],
            'search' => ['sometimes', 'string', 'max:255'],
            'company_id' => ['sometimes', 'string', 'max:64'],
            'updated_since' => ['sometimes', 'date'],
        ]);

        $perPage = min($request->integer('per_page', 25) ?: 25, self::MAX_PER_PAGE);

        $contacts = Contact::query()
            ->with(self::RELATIONS)
            ->when(
                $request->filled('email'),
                fn (Builder $q) => $q->where(
                    'email_normalized',
                    mb_strtolower(trim((string) $request->input('email'))),
                ),
            )
            ->when(
                $request->filled('search'),
                fn (Builder $q) => $q->where(
                    fn (Builder $inner) => $inner
                        ->where('full_name', 'like', '%'.$request->string('search').'%')
                        ->orWhere('email', 'like', '%'.$request->string('search').'%'),
                ),
            )
            ->when(
                $request->filled('company_id'),
                // Filtered by the company's public uuid, because that is the
                // only company identifier this API ever hands out.
                fn (Builder $q) => $q->whereHas(
                    'companies',
                    fn (Builder $inner) => $inner->where('companies.uuid', $request->string('company_id')),
                ),
            )
            ->when(
                $request->filled('updated_since'),
                fn (Builder $q) => $q->where('updated_at', '>=', $request->date('updated_since')),
            )
            // Ascending by id so a caller paging through the whole list does
            // not see rows shuffle as new contacts arrive mid-crawl.
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return ContactResource::collection($contacts);
    }

    public function show(string $uuid): ContactResource
    {
        return new ContactResource($this->find($uuid)->load([...self::RELATIONS, 'lead']));
    }

    /**
     * Creates a contact, or enriches the one already on file.
     *
     * Matching applies here as it does everywhere else, so posting the same
     * person twice fills gaps in one record rather than creating two. A caller
     * that cares can tell from `meta.is_duplicate`.
     */
    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = app(UpsertContact::class)->handle($request->validated());

        // Set by Eloquent on insert only, so it is exactly the question
        // "was anything new created here".
        $created = $contact->wasRecentlyCreated;

        return (new ContactResource($contact->load(self::RELATIONS)))
            ->additional(['meta' => ['is_duplicate' => ! $created]])
            ->response()
            // 200 for a match: nothing was created, and saying 201 would tell
            // a retrying client it had made a second person.
            ->setStatusCode($created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function update(UpdateContactRequest $request, string $uuid): ContactResource
    {
        $contact = $this->find($uuid);
        $contact->fill($request->validated())->save();

        return new ContactResource($contact->load(self::RELATIONS));
    }

    /**
     * Links this person to an organisation.
     *
     * Its own endpoint because employment is a relationship, not a field: it
     * carries a role, decides which employer is current, and must not be
     * reshuffled by a sync that happens to run last.
     */
    public function attachCompany(Request $request, string $uuid): ContactResource
    {
        $validated = $request->validate([
            'company_id' => ['required', 'string'],
            'role' => ['nullable', 'string', 'max:120'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $contact = $this->find($uuid);

        // Tenant-scoped, so a uuid from another workspace does not resolve and
        // there is no ownership check to forget.
        $company = Company::query()->where('uuid', $validated['company_id'])->firstOrFail();

        $isPrimary = (bool) ($validated['is_primary'] ?? false);

        if ($isPrimary) {
            // Exactly one employer can be current, so promoting one demotes
            // the rest rather than leaving two primaries to argue over.
            $contact->companies()->newPivotQuery()->update(['is_primary' => false]);
        }

        $contact->companies()->syncWithoutDetaching([
            $company->id => [
                'role' => $validated['role'] ?? null,
                'is_primary' => $isPrimary,
            ],
        ]);

        return new ContactResource($contact->load(self::RELATIONS));
    }

    public function detachCompany(string $uuid, string $companyUuid): JsonResponse
    {
        $contact = $this->find($uuid);
        $company = Company::query()->where('uuid', $companyUuid)->firstOrFail();

        $contact->companies()->detach($company->id);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function destroy(string $uuid): JsonResponse
    {
        // Soft delete: an integration bug must not be able to erase a
        // workspace's history (§18).
        $this->find($uuid)->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function find(string $uuid): Contact
    {
        return Contact::query()->where('uuid', $uuid)->firstOrFail();
    }
}
