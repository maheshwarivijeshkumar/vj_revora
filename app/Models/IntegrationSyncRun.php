<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One pass over a connected account, scheduled or by hand (§57).
 *
 * @property int $id
 * @property int $integration_connection_id
 * @property string $trigger
 * @property string $status
 * @property int $leads_fetched
 * @property int $leads_created
 * @property int $leads_merged
 * @property int $leads_skipped
 * @property string|null $error
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 */
final class IntegrationSyncRun extends Model
{
    use BelongsToTenant;

    public const TRIGGER_SCHEDULE = 'schedule';

    public const TRIGGER_MANUAL = 'manual';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    /** @return BelongsTo<IntegrationConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
