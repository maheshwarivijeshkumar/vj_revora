<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Employment: the link between a person and an organisation (§21).
 *
 * A model of its own rather than a bare pivot, because the row carries meaning
 * — a job title and which employer is current — and typing it keeps that
 * readable from both sides of the relationship.
 *
 * @property int $contact_id
 * @property int $company_id
 * @property string|null $role
 *                             Tenant-scoped like everything else it joins. The relation pins `tenant_id`
 *                             on attach, since attaching writes through a query builder rather than the
 *                             model; the trait is what makes a direct query on this table fail closed
 *                             (ADR-009, ADR-010).
 * @property int $tenant_id
 * @property bool $is_primary
 */
final class ContactCompany extends Pivot
{
    use AsPivot, BelongsToTenant;

    protected $table = 'contact_company';

    /**
     * The migration gives this row its own id, so the composite key does not
     * need to stand in for one.
     */
    public $incrementing = true;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
