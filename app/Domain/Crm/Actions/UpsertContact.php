<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Domain\Crm\PersonalEmailDomain;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

/**
 * Creates a person, or enriches the one already on file (§21).
 *
 * Matching uses the same normalised identifiers as lead deduplication, so a
 * contact and the lead it was converted from stay recognisable as the same
 * human however either arrived (§18).
 */
final class UpsertContact
{
    public function __construct(
        private readonly UpsertCompany $companies,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  may carry `company` (a name
     *                                            or domain) and `company_domain`, which are resolved to a Company
     *                                            rather than stored on the contact
     */
    public function handle(array $attributes, ?Company $company = null): Contact
    {
        $company ??= $this->resolveCompany($attributes);

        unset($attributes['company'], $attributes['company_domain'], $attributes['company_id']);

        return DB::transaction(function () use ($attributes, $company): Contact {
            $contact = $this->match($attributes);

            if ($contact instanceof Contact) {
                $this->fillGaps($contact, $attributes);
            } else {
                $contact = Contact::create($attributes);
            }

            if ($company instanceof Company) {
                $this->attach($contact, $company);
            }

            return $contact;
        });
    }

    /**
     * Finds the person this payload is describing.
     *
     * Email first, then phone, both on the normalised column so case and
     * formatting cannot hide a match.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function match(array $attributes): ?Contact
    {
        $email = $this->string($attributes, 'email');

        if ($email !== null) {
            $byEmail = Contact::query()
                ->where('email_normalized', mb_strtolower($email))
                ->first();

            if ($byEmail instanceof Contact) {
                return $byEmail;
            }
        }

        $phone = Lead::normalisePhone($this->string($attributes, 'phone'));

        if ($phone === null) {
            return null;
        }

        return Contact::query()->where('phone_normalized', $phone)->first();
    }

    /**
     * Works out which organisation this person belongs to.
     *
     * A named company is created if it is new. An email domain only ever
     * *matches* an existing company and never creates one, because a host name
     * is not a company name and inventing "acme.example" as an organisation
     * leaves a record nobody can tidy up.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function resolveCompany(array $attributes): ?Company
    {
        $name = $this->string($attributes, 'company');
        $domain = $this->string($attributes, 'company_domain');

        if ($name !== null || $domain !== null) {
            return $this->companies->handle(array_filter([
                'name' => $name ?? $domain,
                'domain' => $domain,
            ], fn (mixed $value): bool => $value !== null));
        }

        $inferred = PersonalEmailDomain::companyDomainFrom($this->string($attributes, 'email'));

        if ($inferred === null) {
            return null;
        }

        return Company::query()->where('domain', $inferred)->first();
    }

    /**
     * Links the person to the organisation.
     *
     * Marked primary only when they have no primary yet: someone who has
     * moved on keeps their history, and a sync must not reshuffle which
     * employer is considered current.
     */
    private function attach(Contact $contact, Company $company): void
    {
        if ($contact->companies()->whereKey($company->id)->exists()) {
            return;
        }

        $contact->companies()->attach($company->id, [
            'is_primary' => ! $contact->companies()->wherePivot('is_primary', true)->exists(),
        ]);

        $contact->unsetRelation('companies');
    }

    /**
     * Fills blanks without overwriting anything (§18).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function fillGaps(Contact $contact, array $attributes): void
    {
        $dirty = false;

        foreach ($attributes as $field => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if (in_array($field, ['id', 'uuid', 'tenant_id'], true)) {
                continue;
            }

            if ($field === 'metadata' && is_array($value)) {
                $merged = [...(is_array($contact->metadata) ? $contact->metadata : []), ...$value];

                if ($merged !== $contact->metadata) {
                    $contact->metadata = $merged;
                    $dirty = true;
                }

                continue;
            }

            if ($contact->{$field} === null || $contact->{$field} === '') {
                $contact->{$field} = $value;
                $dirty = true;
            }
        }

        if ($dirty) {
            $contact->save();
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function string(array $attributes, string $key): ?string
    {
        $value = $attributes[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
