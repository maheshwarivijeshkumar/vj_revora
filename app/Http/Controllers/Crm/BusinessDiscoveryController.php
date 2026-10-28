<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Discovery\Actions\ImportDiscoveredBusinesses;
use App\Domain\Discovery\Actions\SearchBusinesses;
use App\Domain\Discovery\BusinessCatalog;
use App\Domain\Discovery\DataObjects\DiscoveryQuery;
use App\Domain\Discovery\Sources\GooglePlacesSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\ImportBusinessesRequest;
use App\Http\Requests\Crm\SearchBusinessesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Find local businesses by city and add the chosen ones as leads.
 */
final class BusinessDiscoveryController extends Controller
{
    public function index(Request $request, SearchBusinesses $search, GooglePlacesSource $google): Response
    {
        $searchId = $request->string('search')->toString();
        $tenantId = (int) $request->user()?->tenant_id;
        $result = $searchId === '' ? null : $search->recall($tenantId, $searchId);

        return Inertia::render('crm/discovery/Index', [
            'categories' => array_map(fn (array $category): array => [
                'value' => $category['key'],
                'label' => $category['label'],
                'group' => $category['group'],
            ], BusinessCatalog::all()),
            'google_ready' => $google->isConfigured(),
            'search' => $searchId,
            'result' => $result === null ? null : [
                'id' => $searchId,
                'businesses' => $result['businesses'],
                'notice' => $result['notice'],
            ],
            'expired' => $searchId !== '' && $result === null,
            'query' => [
                'city' => $request->string('city')->toString(),
                'state' => $request->string('state')->toString(),
                'country' => $request->string('country')->toString(),
                'category' => $request->string('category')->toString(),
                'source' => $request->string('source')->toString() ?: 'both',
            ],
        ]);
    }

    public function search(SearchBusinessesRequest $request, SearchBusinesses $search): RedirectResponse
    {
        $validated = $request->validated();
        $state = isset($validated['state']) && $validated['state'] !== '' ? $validated['state'] : null;

        $result = $search->handle(new DiscoveryQuery(
            city: $validated['city'],
            state: $state,
            country: $validated['country'],
            category: $validated['category'],
            source: $validated['source'],
        ), (int) $request->user()?->tenant_id);

        return redirect()->route('discovery.index', [
            'search' => $result['id'],
            'city' => $validated['city'],
            'state' => $state,
            'country' => $validated['country'],
            'category' => $validated['category'],
            'source' => $validated['source'],
        ]);
    }

    public function import(ImportBusinessesRequest $request, ImportDiscoveredBusinesses $import): RedirectResponse
    {
        $validated = $request->validated();
        $counts = $import->handle(
            (int) $request->user()?->tenant_id,
            $validated['search'],
            $validated['ids'],
        );

        $message = $counts['created'] === 1
            ? '1 business added as a lead.'
            : $counts['created'].' businesses added as leads.';

        if ($counts['duplicates'] > 0) {
            $message .= ' '.$counts['duplicates'].' already in the CRM were left as they are.';
        }

        if ($counts['created'] === 0 && $counts['duplicates'] === 0) {
            $message = 'That search expired. Run it again, then add the businesses you want.';
        }

        return redirect()->route('discovery.index', [
            'search' => $validated['search'],
        ])->with('success', $message);
    }
}
