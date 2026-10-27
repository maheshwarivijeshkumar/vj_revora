<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Opportunities\Enums\OpportunityStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * How well one tender or job post fits this workspace, and what was done.
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $opportunity_type tender|job_post
 * @property int $opportunity_id
 * @property int|null $service_profile_id
 * @property int $fit_score
 * @property array{services?: list<string>, keywords?: list<string>, reasons?: list<string>}|null $fit_details
 * @property OpportunityStatus $status
 * @property string|null $draft
 * @property string|null $drafted_by
 * @property Carbon|null $drafted_at
 * @property string|null $notes
 * @property int|null $lead_id
 * @property int|null $updated_by
 * @property Carbon|null $created_at
 */
final class OpportunityMatch extends Model
{
    use BelongsToTenant;

    public const TYPE_TENDER = 'tender';

    public const TYPE_JOB_POST = 'job_post';

    /** Shown by default: a good fit on more than a passing keyword. */
    public const HIGH_FIT = 60;

    /** Below this a match is not stored at all. */
    public const MIN_FIT = 35;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $match): void {
            $match->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<ServiceProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ServiceProfile::class, 'service_profile_id');
    }

    /** @return BelongsTo<Tender, $this> */
    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class, 'opportunity_id');
    }

    /** @return BelongsTo<JobPost, $this> */
    public function jobPost(): BelongsTo
    {
        return $this->belongsTo(JobPost::class, 'opportunity_id');
    }

    public function opportunity(): Tender|JobPost|null
    {
        return $this->opportunity_type === self::TYPE_TENDER
            ? Tender::query()->find($this->opportunity_id)
            : JobPost::query()->find($this->opportunity_id);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fit_details' => 'array',
            'status' => OpportunityStatus::class,
            'drafted_at' => 'datetime',
        ];
    }
}
