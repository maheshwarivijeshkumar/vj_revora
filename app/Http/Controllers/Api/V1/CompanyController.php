<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Crm\Actions\UpsertCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCompanyRequest;
use App\Http\Requests\Api\V1\UpdateCompanyRequest;
use App\Http\Resources\Api\V1\CompanyResource;
use App\Http\Resources\Api\V1\ContactResource;
use App\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public company endpoints (§47).
 */
final class CompanyController extends Controller
{
    private const MAX_PER_PAGE = 100;

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'domain' => ['sometimes', 'string', 'max:255'],
            'search' => ['sometimes', 'string', 'max:255'],
            'updated_since' => ['sometimes', 'date'],
        ]);

        $perPage = min($request->integer('per_page', 25) ?: 25, self::MAX_PER_PAGE);

        $companies = Company::query()
            ->with('owner')
            ->withCount(['contacts', 'deals'])
            ->when(
                $request->filled('domain'),
                // Normalised the same way on the way in, so a caller may send
                // a full URL and still match.
                fn (Builder $q) => $q->where(
                    'domain',
                    Company::normaliseDomain((string) $request->input('domain')),
                ),
            )
            ->when(
                $request->filled('search'),
                fn (Builder $q) => $q->where('name', 'like', '%'.$request->string('search').'%'),
            )
            ->when(
                $request->filled('updated_since'),
                fn (Builder $q) => $q->where('updated_at', '>=', $request->date('updated_since')),
            )
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return CompanyResource::collection($companies);
    }

    public function show(string $uuid): CompanyResource
    {
        return new CompanyResource(
            $this->find($uuid)->loadCount(['contacts', 'deals'])->load('owner'),
        );
    }

    /**
     * Creates a company, or enriches the one already on file.
     *
     * Matched on domain first and exact name second, because two Acmes on the
     * board is a data problem no reporting can recover from (§18).
     */
    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = app(UpsertCompany::class)->handle($request->validated());

        $created = $company->wasRecentlyCreated;

        return (new CompanyResource($company->loadCount(['contacts', 'deals'])))
            ->additional(['meta' => ['is_duplicate' => ! $created]])
            ->response()
            ->setStatusCode($created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function update(UpdateCompanyRequest $request, string $uuid): CompanyResource
    {
        $company = $this->find($uuid);
        $company->fill($request->validated())->save();

        return new CompanyResource($company->loadCount(['contacts', 'deals'])->load('owner'));
    }

    /**
     * The people at this company.
     *
     * Its own endpoint rather than an ever-growing embedded array, because a
     * company can have thousands of contacts and a single response is the
     * wrong shape for that.
     */
    public function contacts(Request $request, string $uuid): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 25) ?: 25, self::MAX_PER_PAGE);

        $contacts = $this->find($uuid)
            ->contacts()
            ->with('owner')
            ->orderByPivot('is_primary', 'desc')
            ->orderBy('contacts.id')
            ->paginate($perPage)
            ->withQueryString();

        return ContactResource::collection($contacts);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->find($uuid)->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    private function find(string $uuid): Company
    {
        return Company::query()->where('uuid', $uuid)->firstOrFail();
    }
}
