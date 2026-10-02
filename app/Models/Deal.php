<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Deals\Enums\DealStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use App\Domain\Tenancy\TenantContext;
use App\Observers\AuditObserver;
use App\Observers\DealObserver;
use Carbon\CarbonInterface;
use Database\Factories\DealFactory;
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
 * An opportunity moving through a pipeline (§21, §22).
 *
 * @property int $id
 * @property string $uuid
 * @property string $title
 * @property string $value
 * @property string $currency
 * @property int $pipeline_id
 * @property int $pipeline_stage_id
 * @property int $probability
 * @property DealStatus $status
 * @property int|null $owner_id
 * @property int $position
 * @property string|null $lost_reason
 * @property CarbonInterface|null $expected_close_date
 * @property CarbonInterface|null $closed_at
 * @property CarbonInterface|null $created_at
 * @property array<string, mixed>|null $metadata
 */
#[ObservedBy([DealObserver::class, AuditObserver::class])]
final class Deal extends Model
{
    /** @use HasFactory<DealFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $deal): void {
            $deal->uuid ??= (string) Str::uuid7();
        });
    }

    /**
     * Value adjusted by likelihood, which is what a forecast actually is.
     *
     * Returned as a float for arithmetic; the stored value stays decimal so
     * currency amounts never drift.
     */
    public function weightedValue(): float
    {
        return round((float) $this->value * ($this->probability / 100), 2);
    }

    public function isOpen(): bool
    {
        return $this->status === DealStatus::Open;
    }

    // --- Relations ----------------------------------------------------------

    /** @return BelongsTo<Pipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /** @return BelongsTo<PipelineStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<DealStageChange, $this> */
    public function stageChanges(): HasMany
    {
        return $this->hasMany(DealStageChange::class)->orderByDesc('changed_at');
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
     * @param  Builder<Deal>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('status', DealStatus::Open->value);
    }

    /**
     * Board order: the position a person dragged the card to, newest first
     * within a tie.
     *
     * @param  Builder<Deal>  $query
     */
    public function scopeBoardOrder(Builder $query): void
    {
        $query->orderBy('position')->orderByDesc('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DealStatus::class,
            'value' => 'decimal:4',
            'expected_close_date' => 'date',
            'closed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
