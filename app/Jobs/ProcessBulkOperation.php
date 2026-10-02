<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Bulk\Actions\RunBulkLeadAction;
use App\Domain\Bulk\Enums\BulkStatus;
use App\Jobs\Concerns\RunsInTenantContext;
use App\Models\BulkOperation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs a bulk action too large for a request (§113).
 */
final class ProcessBulkOperation implements ShouldQueue
{
    use Queueable, RunsInTenantContext;

    /**
     * One attempt.
     *
     * A retry would reapply the action to records it already changed. The
     * operation row records where it got to, so a failure is resumable by a
     * human decision rather than silently repeated.
     */
    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        private readonly int $operationId,
    ) {
        $this->captureTenant();
        $this->onQueue('bulk');
    }

    public function handle(RunBulkLeadAction $leads): void
    {
        $this->inTenantContext(function () use ($leads): void {
            $operation = BulkOperation::query()->find($this->operationId);

            if ($operation === null) {
                return;
            }

            $leads->handle($operation);
        });
    }

    public function failed(?Throwable $exception): void
    {
        $this->inTenantContext(function () use ($exception): void {
            $operation = BulkOperation::query()->find($this->operationId);

            // Recorded, not left pending: a progress poll that never resolves
            // is worse than a failure the user can see and retry.
            $operation?->forceFill([
                'status' => BulkStatus::Failed,
                'errors' => [Str::limit($exception?->getMessage() ?? 'The operation failed.', 300)],
                'finished_at' => now(),
            ])->save();
        });
    }
}
