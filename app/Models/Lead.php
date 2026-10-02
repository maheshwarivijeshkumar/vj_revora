<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Enums\ScoreBand;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\TenantContext;
use App\Observers\AuditObserver;
use App\Observers\LeadObserver;
use Carbon\CarbonInterface;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A lead: the record everything else in the product hangs off (§17, §85).
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $full_name
 * @property string|null $email
 * @property string|null $email_normalized
 * @property string|null $phone
 * @property string|null $phone_normalized
 * @property string|null $company_name
 * @property LeadStatus $status
 * @property int $score
 * @property ScoreBand $score_band
 * @property int|null $owner_id
 * @property int|null $team_id
 * @property int|null $lead_source_id
 * @property int|null $merged_into_id
 * @property array<string, string>|null $utm
 * @property array<string, mixed>|null $first_touch
 * @property array<string, mixed>|null $last_touch
 * @property array<string, mixed>|null $metadata
 * @property bool $consent
 * @property CarbonInterface|null $consent_at
 * @property CarbonInterface|null $last_activity_at
 * @property CarbonInterface|null $next_follow_up_at
 * @property CarbonInterface|null $qualified_at
 * @property CarbonInterface|null $converted_at
 */
#[ObservedBy([LeadObserver::class, AuditObserver::class])]
final class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * True while the capture pipeline is still working on this lead.
     *
     * Transient and never persisted. The pipeline inserts the lead and then
     * saves it again to record the score and the owner; those saves are part of
     * creating it, so anything reacting to an update - webhooks, automations,
     * notifications - waits for LeadCaptured instead of firing three times per
     * lead (§49, §90).
     */
    public bool $isBeingCaptured = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $lead): void {
            $lead->uuid ??= (string) Str::uuid7();
        });

        // Runs on create *and* update: an email corrected by a rep must
        // re-normalise, or deduplication silently stops matching it.
        self::saving(function (self $lead): void {
            $lead->normaliseIdentifiers();
            $lead->composeFullName();
        });
    }

    /**
     * Derives the matchable forms of email and phone.
     *
     * Done here rather than as generated columns because E.164 normalisation
     * is not expressible in SQL, and splitting the two across layers would be
     * worse than keeping both in one place.
     */
    public function normaliseIdentifiers(): void
    {
        $email = $this->email === null ? null : Str::lower(trim($this->email));
        $this->email_normalized = $email === '' ? null : $email;

        $this->phone_normalized = self::normalisePhone($this->phone);
    }

    /**
     * Reduces a phone number to a comparable form.
     *
     * Keeps a leading + and digits only. Deliberately not a full libphonenumber
     * parse: without a reliable country for every lead, guessing a region does
     * more harm than good. Two numbers written differently but dialling the
     * same destination still collapse to the same string here.
     */
    public static function normalisePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $trimmed = trim($phone);
        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        if ($digits === '') {
            return null;
        }

        return str_starts_with($trimmed, '+') ? '+'.$digits : $digits;
    }

    /** Keeps `full_name` consistent with its parts, without discarding it. */
    public function composeFullName(): void
    {
        $composed = trim(implode(' ', array_filter([$this->first_name, $this->last_name])));

        if ($composed !== '') {
            $this->full_name = $composed;
        }
    }

    /** A name to show when the lead gave no name at all. */
    public function displayName(): string
    {
        return $this->full_name
            ?: $this->email
            ?: $this->phone
            ?: "Lead #{$this->id}";
    }

    public function isMerged(): bool
    {
        return $this->merged_into_id !== null;
    }

    // --- Relations ----------------------------------------------------------

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<LeadSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    /** @return BelongsTo<Lead, $this> */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_id');
    }

    /** @return HasMany<LeadEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(LeadEvent::class)->orderByDesc('occurred_at');
    }

    /** @return HasMany<LeadScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(LeadScore::class)->orderByDesc('computed_at');
    }

    /** @return HasMany<LeadAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(LeadAssignment::class)->orderByDesc('assigned_at');
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

    // --- Scopes -------------------------------------------------------------

    /**
     * Excludes duplicates that were merged away.
     *
     * Almost every list and count wants this: a merged lead still exists so
     * its history survives, but showing it would double-count the person.
     *
     * @param  Builder<Lead>  $query
     */
    public function scopeMaster(Builder $query): void
    {
        $query->whereNull('merged_into_id');
    }

    /**
     * @param  Builder<Lead>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn(
            'status',
            array_map(
                fn (LeadStatus $s): string => $s->value,
                array_filter(LeadStatus::cases(), fn (LeadStatus $s): bool => $s->isOpen()),
            ),
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'score_band' => ScoreBand::class,
            'utm' => 'array',
            'first_touch' => 'array',
            'last_touch' => 'array',
            'metadata' => 'array',
            'consent' => 'boolean',
            'consent_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'next_follow_up_at' => 'datetime',
            'qualified_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }
}
