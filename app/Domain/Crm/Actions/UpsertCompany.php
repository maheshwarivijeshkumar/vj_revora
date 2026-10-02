<?php

declare(strict_types=1);

namespace App\Domain\Crm\Actions;

use App\Models\Company;
use Illuminate\Support\Str;

/**
 * Creates an organisation, or enriches the one already on file (§21).
 *
 * An upsert rather than a create, for the same reason leads are deduplicated
 * (§18): a connector syncing nightly and a form filled twice both arrive as
 * "Acme", and two Acmes on the board is a data problem no reporting can
 * recover from.
 */
final class UpsertCompany
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes): Company
    {
        $domain = Company::normaliseDomain(
            $this->string($attributes, 'domain') ?? $this->string($attributes, 'website'),
        );

        $existing = $this->match($domain, $this->string($attributes, 'name'));

        if ($existing instanceof Company) {
            $this->fillGaps($existing, $attributes);

            return $existing;
        }

        return Company::create($attributes);
    }

    /**
     * Finds the company this payload is describing.
     *
     * Domain first, because it is the one identifier two systems agree on;
     * a name is matched only as a fallback and only exactly, since "Acme" and
     * "Acme Holdings" are routinely different companies.
     */
    private function match(?string $domain, ?string $name): ?Company
    {
        if ($domain !== null) {
            $byDomain = Company::query()->where('domain', $domain)->first();

            if ($byDomain instanceof Company) {
                return $byDomain;
            }
        }

        if ($name === null) {
            return null;
        }

        return Company::query()->whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();
    }

    /**
     * Fills blanks without overwriting anything.
     *
     * The record on file was put there by some process that had its own
     * reasons; a later payload is not automatically more correct, so a
     * conflict is left alone for a human rather than resolved by whoever
     * wrote last (§18).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function fillGaps(Company $company, array $attributes): void
    {
        $dirty = false;

        foreach ($attributes as $field => $value) {
            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            // Merged rather than replaced, so a second touch adds its
            // attribution instead of discarding the first.
            if ($field === 'metadata' && is_array($value)) {
                $merged = [...(is_array($company->metadata) ? $company->metadata : []), ...$value];

                if ($merged !== $company->metadata) {
                    $company->metadata = $merged;
                    $dirty = true;
                }

                continue;
            }

            if (in_array($field, ['id', 'uuid', 'tenant_id'], true)) {
                continue;
            }

            if ($company->{$field} === null || $company->{$field} === '') {
                $company->{$field} = $value;
                $dirty = true;
            }
        }

        if ($dirty) {
            $company->save();
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
