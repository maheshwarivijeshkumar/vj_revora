<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Messaging\Enums\EnrollmentStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A lead's progress through a follow-up sequence.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $lead_id
 * @property int $follow_up_sequence_id
 * @property int|null $email_account_id
 * @property EnrollmentStatus $status
 * @property int $next_position
 * @property Carbon|null $next_run_at
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property string|null $last_error
 */
final class FollowUpEnrollment extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<FollowUpSequence, $this> */
    public function sequence(): BelongsTo
    {
        return $this->belongsTo(FollowUpSequence::class, 'follow_up_sequence_id');
    }

    /** @return BelongsTo<EmailAccount, $this> */
    public function emailAccount(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'next_run_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
