<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Integrations\Actions\SyncLeadAdsConnection;
use App\Jobs\Concerns\RunsInTenantContext;
use App\Models\IntegrationConnection;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Syncs one connected lead-ad account on the queue (§57, §84).
 *
 * Unique per connection, so a slow run and the next hourly tick, or a "Sync
 * now" click during a scheduled run, cannot both page through the same
 * account at once.
 */
final class SyncLeadAdsConnectionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable, RunsInTenantContext;

    /** Failures are recorded on the run and retried by the next tick instead. */
    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public function __construct(
        private readonly int $connectionId,
        private readonly string $trigger,
    ) {
        $this->captureTenant();
        $this->onQueue('integrations');
    }

    public function uniqueId(): string
    {
        return (string) $this->connectionId;
    }

    public function handle(SyncLeadAdsConnection $sync): void
    {
        $this->inTenantContext(function () use ($sync): void {
            $connection = IntegrationConnection::query()->find($this->connectionId);

            if ($connection === null || ! $connection->status->isSyncable()) {
                return;
            }

            $sync->handle($connection, $this->trigger);
        });
    }
}
