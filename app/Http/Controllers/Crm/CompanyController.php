<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The companies list (§21, §110–115).
 */
final class CompanyController extends Controller
{
    private const PAGE_SIZES = [10, 25, 50, 100, 250];

    /**
     * Columns a client may sort by.
     *
     * The two counts are included because "our biggest accounts" is the reason
     * this list gets opened, and sorting them in the browser would only sort
     * the page you can already see.
     */
    private const SORTABLE = [
        'name', 'domain', 'industry', 'created_at',
        'contacts_count', 'deals_count',
    ];

    public function index(Request $request): Response
    {
        $perPage = (int) $request->integer('per_page', 25);
        $perPage = in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 25;

        [$sort, $direction] = $this->sort($request);

        $query = Company::query()
            ->with('owner:id,name')
            ->withCount(['contacts', 'deals']);

        $this->applySearch($query, $request->string('search')->trim()->toString());

        $query
            ->when(
                $request->filled('industry'),
                fn (Builder $q) => $q->whereIn('industry', (array) $request->input('industry')),
            )
            ->when(
                $request->filled('owner_id'),
                fn (Builder $q) => $q->whereIn('owner_id', (array) $request->input('owner_id')),
            );

        $companies = $query
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Company $company): array => $this->row($company));

        return Inertia::render('crm/companies/Index', [
            'companies' => $companies,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'industry' => $request->input('industry', []),
                'owner_id' => $request->input('owner_id', []),
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'options' => Inertia::defer(fn (): array => [
                'owners' => User::query()
                    ->where('status', 'active')
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (User $u): array => ['value' => (string) $u->id, 'label' => $u->name])
                    ->all(),
                // Drawn from the data rather than a fixed list, because the
                // industries that matter are the ones this workspace actually
                // sells to.
                'industries' => Company::query()
                    ->whereNotNull('industry')
                    ->distinct()
                    ->orderBy('industry')
                    ->pluck('industry')
                    ->map(fn (string $industry): array => ['value' => $industry, 'label' => $industry])
                    ->all(),
            ]),
            'pageSizes' => self::PAGE_SIZES,
        ]);
    }

    /**
     * @param  Builder<Company>  $query
     */
    private function applySearch(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query->where(function (Builder $q) use ($like): void {
            $q->where('name', 'like', $like)
                ->orWhere('domain', 'like', $like)
                ->orWhere('website', 'like', $like);
        });
    }

    /**
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    private function sort(Request $request): array
    {
        $sort = $request->string('sort')->toString();
        $direction = strtolower($request->string('direction')->toString());

        return [
            in_array($sort, self::SORTABLE, true) ? $sort : 'created_at',
            $direction === 'asc' ? 'asc' : 'desc',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Company $company): array
    {
        return [
            'id' => $company->id,
            'uuid' => $company->uuid,
            'name' => $company->name,
            'domain' => $company->domain,
            'website' => $company->website,
            'industry' => $company->industry,
            'size' => $company->size,
            'country' => $company->country,
            // Carried even though no column displays it, because the edit form
            // submits every field: a row without it would send phone = null and
            // quietly wipe the number on the first save.
            'phone' => $company->phone,
            'contacts_count' => $company->contacts_count,
            'deals_count' => $company->deals_count,
            'owner' => $company->owner?->name,
            'owner_id' => $company->owner_id,
            'created_at' => $company->created_at?->toIso8601String(),
        ];
    }
}
