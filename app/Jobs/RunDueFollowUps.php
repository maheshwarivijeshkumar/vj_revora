<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Messaging\Actions\AdvanceFollowUp;
use App\Domain\Messaging\Enums\EnrollmentStatus;
use App\Jobs\Concerns\RunsInTenantContext;
use App\Models\FollowUpEnrollment;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Advances every follow-up that is due in one workspace.
 *
 * Unique per workspace, so a slow batch and the next minute's tick cannot
 * both send the same step.
 */
final class RunDueFollowUps implements ShouldBeUnique, ShouldQueue
{
    use Queueable, RunsInTenantContext;

    /** A large backlog is worked off over several ticks rather than in one. */
    private const BATCH = 200;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    public function __construct()
    {
        $this->captureTenant();
        $this->onQueue('messaging');
    }

    public function uniqueId(): string
    {
        return (string) $this->tenantId;
    }

    public function handle(AdvanceFollowUp $advance): void
    {
        $this->inTenantContext(function () use ($advance): void {
            FollowUpEnrollment::query()
                ->where('status', EnrollmentStatus::Active->value)
                ->where('next_run_at', '<=', now())
                ->orderBy('next_run_at')
                ->limit(self::BATCH)
                ->get()
                ->each(fn (FollowUpEnrollment $enrollment) => $advance->handle($enrollment));
        });
    }
}
