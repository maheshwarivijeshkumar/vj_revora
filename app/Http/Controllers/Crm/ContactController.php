<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The contacts list (§21, §110–115).
 *
 * Search, filtering, sorting and pagination all happen in the database, for the
 * reason §112 gives: thousands of rows must never reach the browser just so it
 * can paginate them.
 */
final class ContactController extends Controller
{
    private const PAGE_SIZES = [10, 25, 50, 100, 250];

    /** Columns a client may sort by. Anything else is ignored. */
    private const SORTABLE = ['full_name', 'email', 'job_title', 'created_at'];

    public function index(Request $request): Response
    {
        $perPage = (int) $request->integer('per_page', 25);
        $perPage = in_array($perPage, self::PAGE_SIZES, true) ? $perPage : 25;

        [$sort, $direction] = $this->sort($request);

        $query = Contact::query()->with(['owner:id,name', 'companies:id,name']);

        $this->applySearch($query, $request->string('search')->trim()->toString());

        $query
            ->when(
                $request->filled('owner_id'),
                fn (Builder $q) => $q->whereIn('owner_id', (array) $request->input('owner_id')),
            )
            ->when(
                $request->filled('company_id'),
                fn (Builder $q) => $q->whereHas(
                    'companies',
                    fn (Builder $inner) => $inner->whereIn('companies.id', (array) $request->input('company_id')),
                ),
            );

        $contacts = $query
            ->orderBy($sort, $direction)
            // A stable tiebreak, otherwise rows shuffle between pages when many
            // share the same sort value.
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Contact $contact): array => $this->row($contact));

        return Inertia::render('crm/contacts/Index', [
            'contacts' => $contacts,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'owner_id' => $request->input('owner_id', []),
                'company_id' => $request->input('company_id', []),
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            // Deferred: the filter panel starts closed, so its options need not
            // block the table rendering (Inertia v3).
            'options' => Inertia::defer(fn (): array => [
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
            ]),
            'pageSizes' => self::PAGE_SIZES,
        ]);
    }

    /**
     * Grouped so a filter cannot be escaped by an OR: without the closure, a
     * search term plus an owner filter would return every match regardless of
     * owner.
     *
     * @param  Builder<Contact>  $query
     */
    private function applySearch(Builder $query, string $term): void
    {
        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query->where(function (Builder $q) use ($like): void {
            $q->where('full_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('job_title', 'like', $like);
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
    private function row(Contact $contact): array
    {
        $primary = $contact->companies->first();

        return [
            'id' => $contact->id,
            'uuid' => $contact->uuid,
            'name' => $contact->displayName(),
            'email' => $contact->email,
            'phone' => $contact->phone,
            'job_title' => $contact->job_title,
            'company' => $primary?->name,
            'companies_count' => $contact->companies->count(),
            'owner' => $contact->owner?->name,
            'created_at' => $contact->created_at?->toIso8601String(),

            // The fields the edit form needs, carried on the row so opening the
            // drawer does not cost a second request for a record the table
            // already delivered.
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'country' => $contact->country,
            'owner_id' => $contact->owner_id,
            'company_id' => $primary?->id,
            'company_role' => $primary?->pivot?->role,
        ];
    }
}
