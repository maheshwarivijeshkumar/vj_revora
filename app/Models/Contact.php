<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\TenantContext;
use App\Observers\AuditObserver;
use App\Observers\ContactObserver;
use Database\Factories\ContactFactory;
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
 * A person (§21).
 *
 * Shares the lead model's identifier normalisation, so a contact and the lead
 * it was converted from stay matchable on the same rules.
 *
 * @property int $id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $full_name
 * @property string|null $email
 * @property string|null $email_normalized
 * @property string|null $phone
 * @property string|null $phone_normalized
 * @property array<string, mixed>|null $metadata
 * @property-read ContactCompany|null $pivot the
 *   contact_company row, present only on an instance loaded through
 *   Company::contacts()
 */
#[ObservedBy([ContactObserver::class, AuditObserver::class])]
final class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $contact): void {
            $contact->uuid ??= (string) Str::uuid7();
        });

        self::saving(function (self $contact): void {
            $email = $contact->email === null ? null : Str::lower(trim($contact->email));
            $contact->email_normalized = $email === '' ? null : $email;
            $contact->phone_normalized = Lead::normalisePhone($contact->phone);

            $composed = trim(implode(' ', array_filter([$contact->first_name, $contact->last_name])));

            if ($composed !== '') {
                $contact->full_name = $composed;
            }
        });
    }

    public function displayName(): string
    {
        return $this->full_name ?: $this->email ?: "Contact #{$this->id}";
    }

    /**
     * The organisations this person is associated with.
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
     * @return BelongsToMany<Company, $this, ContactCompany>
     */
    public function companies(): BelongsToMany
    {
        $tenantId = app(TenantContext::class)->id();

        $relation = $this->belongsToMany(Company::class, 'contact_company')
            ->using(ContactCompany::class)
            ->withPivot(['role', 'is_primary'])
            ->withTimestamps();

        return $tenantId === null
            ? $relation
            : $relation->withPivotValue('tenant_id', $tenantId);
    }

    /** The company this person is primarily associated with. */
    public function primaryCompany(): ?Company
    {
        return $this->companies()->wherePivot('is_primary', true)->first()
            ?? $this->companies()->first();
    }

    /** @return HasMany<Deal, $this> */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
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
