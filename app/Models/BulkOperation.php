<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Bulk\Enums\BulkAction;
use App\Domain\Bulk\Enums\BulkStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One bulk action and how far it has got (§113).
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property int|null $user_id
 * @property string $entity
 * @property BulkAction $action
 * @property list<int> $ids
 * @property array<string, mixed>|null $payload
 * @property BulkStatus $status
 * @property int $total
 * @property int $processed
 * @property int $failed
 * @property list<string>|null $errors
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 */
final class BulkOperation extends Model
{
    use BelongsToTenant;

    /**
     * Above this, the work is queued instead of run in the request.
     *
     * Below it, running inline means the user sees the result immediately rather
     * than a progress bar for something that took 80ms. The number is chosen so
     * the inline path stays well inside a request's budget even when every
     * record fires observers, webhooks and an audit row.
     */
    public const INLINE_LIMIT = 200;

    /**
     * How many records are loaded at once.
     *
     * Bulk actions iterate models rather than issuing one mass UPDATE, because a
     * mass update bypasses observers — no audit row, no webhook, no normalised
     * columns. Chunking is what keeps that affordable.
     */
    public const CHUNK = 100;

    /** The most records one action may touch. */
    public const MAX_IDS = 10000;

    protected $guarded = [];

    /**
     * Bound by uuid, so a progress poll cannot walk sequential ids looking for
     * other people's operations.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected static function booted(): void
    {
        self::creating(function (self $operation): void {
            $operation->uuid ??= (string) Str::uuid7();
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function progress(): int
    {
        return $this->total === 0
            ? 100
            : (int) floor($this->processed / $this->total * 100);
    }

    /**
     * A sentence a human can act on (§59, §113).
     */
    public function summary(): string
    {
        $succeeded = $this->processed - $this->failed;
        $noun = $this->total === 1 ? $this->entity : Str::plural($this->entity);

        if ($this->failed === 0) {
            return "{$succeeded} {$noun} updated.";
        }

        return "{$succeeded} of {$this->total} {$noun} updated; {$this->failed} skipped.";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => BulkAction::class,
            'status' => BulkStatus::class,
            'ids' => 'array',
            'payload' => 'array',
            'errors' => 'array',
            'finished_at' => 'datetime',
        ];
    }
}
