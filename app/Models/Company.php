<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\TenantContext;
use App\Observers\AuditObserver;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * An organisation (§21).
 *
 * @property int $id
 * @property string $name
 * @property string|null $domain
 * @property string|null $website
 * @property array<string, mixed>|null $metadata
 * @property-read ContactCompany|null $pivot the
 *   contact_company row, present only on an instance loaded through
 *   Contact::companies()
 */
#[ObservedBy(AuditObserver::class)]
final class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $company): void {
            $company->uuid ??= (string) Str::uuid7();
        });

        self::saving(function (self $company): void {
            // Normalised so matching a contact's email domain against a
            // company works regardless of how the website was typed.
            //
            // An explicit domain wins. Otherwise a changed website redefines
            // it, because someone correcting the website expects matching to
            // follow rather than to keep using the old host silently.
            if ($company->isDirty('domain')) {
                $company->domain = self::normaliseDomain($company->domain);

                return;
            }

            if ($company->isDirty('website') || $company->domain === null) {
                $company->domain = self::normaliseDomain($company->website);
            }
        });
    }

    /** Reduces a URL or bare domain to a comparable host. */
    public static function normaliseDomain(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $trimmed = trim($value);
        $host = parse_url($trimmed, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            $host = $trimmed;
        }

        $host = Str::lower(Str::before($host, '/'));
        $host = Str::startsWith($host, 'www.') ? Str::after($host, 'www.') : $host;

        return $host === '' ? null : $host;
    }

    /**
     * The people associated with this organisation.
     *
     * The pivot table is named explicitly: Laravel would derive
     * `company_contact` alphabetically, and the migration created
     * `contact_company`.
     *
     * `tenant_id` is pinned from the bound tenant rather than from
     * `$this->tenant_id`, because eager loading resolves a relation on a blank
     * instance where that attribute is not set yet. Pinning fills the column on
     * attach and scopes the join, so the pivot cannot become the one table that
     * leaks across workspaces (ADR-009). With no tenant bound there is nothing
     * to pin, and the global scope on the related model still fails closed
     * while the column's NOT NULL refuses a write.
     *
     * @return BelongsToMany<Contact, $this, ContactCompany>
     */
    public function contacts(): BelongsToMany
    {
        $tenantId = app(TenantContext::class)->id();

        $relation = $this->belongsToMany(Contact::class, 'contact_company')
            ->using(ContactCompany::class)
            ->withPivot(['role', 'is_primary'])
            ->withTimestamps();

        return $tenantId === null
            ? $relation
            : $relation->withPivotValue('tenant_id', $tenantId);
    }

    /** @return HasMany<Deal, $this> */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Labels applied to this record.
     *
     * `tenant_id` is pinned from the bound tenant, for the reason given on
     * Contact::companies(): the pivot carries the column NOT NULL, attaching
     * writes through a query builder rather than the model, and eager loading
     * resolves the relation on a blank instance where $this->tenant_id is not
     * set yet (ADR-009).
     *
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        $tenantId = app(TenantContext::class)->id();

        $relation = $this->morphToMany(Tag::class, 'taggable')->withTimestamps();

        return $tenantId === null
            ? $relation
            : $relation->withPivotValue('tenant_id', $tenantId);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
