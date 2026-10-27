<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Activity\Enums\TaskPriority;
use App\Domain\Activity\Enums\TaskStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Something that needs doing (§21).
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property CarbonInterface|null $due_at
 * @property CarbonInterface|null $completed_at
 * @property int|null $assigned_to
 * @property int|null $created_by
 * @property CarbonInterface|null $created_at
 */
final class Task extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $task): void {
            $task->uuid ??= (string) Str::uuid7();
        });
    }

    /**
     * Bound by uuid, so a sequential id is never in a URL.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Past its due date and still wanting doing.
     *
     * A finished task cannot be overdue, however late it was finished —
     * otherwise a completed list fills with red that nobody can clear.
     */
    public function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->status->isOpen()
            && $this->due_at->isPast();
    }

    /**
     * Open tasks, most pressing first.
     *
     * Overdue before upcoming, then by priority, then by date. Tasks with no
     * due date sort last rather than first: an undated task is a someday, and
     * putting it above tomorrow's call is how a list stops being trusted.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOutstanding(Builder $query): void
    {
        $query
            ->whereIn('status', [TaskStatus::Open->value, TaskStatus::InProgress->value])
            // Undated last rather than first: an undated task is a someday,
            // and putting it above tomorrow's call is how a list stops being
            // trusted. Both MySQL and PostgreSQL order false before true.
            ->orderByRaw('due_at IS NULL')
            // CASE rather than MySQL's FIELD(): ADR-008 keeps every query
            // portable for the PostgreSQL move, and FIELD() does not exist
            // there.
            ->orderByRaw(
                "CASE priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END",
            )
            ->orderBy('due_at')
            ->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
