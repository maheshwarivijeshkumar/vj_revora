<?php

declare(strict_types=1);

namespace App\Domain\Search;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cross-entity search behind the command palette (§45, §120).
 *
 * Deliberately SQL rather than a search engine for now: the result set a
 * palette shows is five rows per entity, the columns involved are indexed, and
 * adding Meilisearch would mean an index that can be stale and a service that
 * can be down in exchange for relevance nobody has asked for yet. Swapping the
 * implementation later only touches this class.
 *
 * Two properties are not negotiable (§120): results never cross a workspace,
 * which the global scope handles, and an entity the user cannot view is not
 * searched at all rather than searched and filtered afterwards.
 */
final class SearchService
{
    /** Per entity. Enough to recognise the one you meant, few enough to scan. */
    private const LIMIT = 5;

    /**
     * Shorter than this and every lead in the workspace matches, which is not
     * a search result, it is a list.
     */
    public const MIN_LENGTH = 2;

    /**
     * @return Collection<int, SearchGroup>
     */
    public function search(string $term, User $user): Collection
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return new Collection;
        }

        return (new Collection([
            $this->leads($term, $user),
            $this->contacts($term, $user),
            $this->companies($term, $user),
            $this->deals($term, $user),
        ]))
            ->filter(fn (?SearchGroup $group): bool => $group instanceof SearchGroup && $group->hits !== [])
            ->values();
    }

    private function leads(string $term, User $user): ?SearchGroup
    {
        if (! $user->can('lead.view')) {
            return null;
        }

        $hits = Lead::query()
            ->master()
            ->where(fn (Builder $q) => $q
                ->where('full_name', 'like', $this->like($term))
                ->orWhere('email', 'like', $this->like($term))
                ->orWhere('company_name', 'like', $this->like($term))
                // Matched on the normalised column as well, so a search for a
                // number typed with spaces still finds the lead.
                ->orWhere('phone_normalized', 'like', $this->like(Lead::normalisePhone($term) ?? $term)))
            ->orderByDesc('score')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Lead $lead): SearchHit => new SearchHit(
                id: $lead->uuid,
                title: $lead->displayName(),
                subtitle: $this->subtitle([$lead->company_name, $lead->email]),
                badge: $lead->status->label(),
                href: '/leads?search='.urlencode($lead->email ?? $lead->displayName()),
            ))
            ->all();

        return new SearchGroup('leads', 'Leads', array_values($hits));
    }

    private function contacts(string $term, User $user): ?SearchGroup
    {
        if (! $user->can('contact.view')) {
            return null;
        }

        $hits = Contact::query()
            ->where(fn (Builder $q) => $q
                ->where('full_name', 'like', $this->like($term))
                ->orWhere('email', 'like', $this->like($term))
                ->orWhere('phone_normalized', 'like', $this->like(Lead::normalisePhone($term) ?? $term)))
            ->orderBy('full_name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Contact $contact): SearchHit => new SearchHit(
                id: $contact->uuid,
                title: $contact->displayName(),
                subtitle: $this->subtitle([$contact->job_title, $contact->email]),
                badge: null,
                href: '/contacts?search='.urlencode($contact->email ?? $contact->displayName()),
            ))
            ->all();

        return new SearchGroup('contacts', 'Contacts', array_values($hits));
    }

    private function companies(string $term, User $user): ?SearchGroup
    {
        if (! $user->can('company.view')) {
            return null;
        }

        $hits = Company::query()
            ->where(fn (Builder $q) => $q
                ->where('name', 'like', $this->like($term))
                ->orWhere('domain', 'like', $this->like($term)))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Company $company): SearchHit => new SearchHit(
                id: $company->uuid,
                title: $company->name,
                subtitle: $this->subtitle([$company->domain, $company->industry]),
                badge: null,
                href: '/companies?search='.urlencode($company->name),
            ))
            ->all();

        return new SearchGroup('companies', 'Companies', array_values($hits));
    }

    private function deals(string $term, User $user): ?SearchGroup
    {
        if (! $user->can('deal.view')) {
            return null;
        }

        $hits = Deal::query()
            ->with('stage')
            ->where('title', 'like', $this->like($term))
            ->orderByDesc('value')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Deal $deal): SearchHit => new SearchHit(
                id: $deal->uuid,
                title: $deal->title,
                subtitle: $this->subtitle([
                    $deal->stage?->name,
                    number_format((float) $deal->value).' '.$deal->currency,
                ]),
                badge: $deal->status->label(),
                href: '/deals',
            ))
            ->all();

        return new SearchGroup('deals', 'Deals', array_values($hits));
    }

    /**
     * Escapes the wildcards a user may well type.
     *
     * Without this, searching for a literal `%` returns the whole table, which
     * reads as a bug rather than as a search.
     */
    private function like(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }

    /**
     * @param  list<string|null>  $parts
     */
    private function subtitle(array $parts): ?string
    {
        $present = array_values(array_filter(
            $parts,
            fn (?string $part): bool => $part !== null && trim($part) !== '',
        ));

        return $present === [] ? null : implode(' · ', $present);
    }
}
