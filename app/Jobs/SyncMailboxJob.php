<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Messaging\Actions\SyncMailbox;
use App\Jobs\Concerns\RunsInTenantContext;
use App\Models\EmailAccount;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Reads one mailbox's inbox for lead replies.
 */
final class SyncMailboxJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable, RunsInTenantContext;

    public int $tries = 1;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    public function __construct(
        private readonly int $accountId,
    ) {
        $this->captureTenant();
        $this->onQueue('messaging');
    }

    public function uniqueId(): string
    {
        return (string) $this->accountId;
    }

    public function handle(SyncMailbox $sync): void
    {
        $this->inTenantContext(function () use ($sync): void {
            $account = EmailAccount::query()->find($this->accountId);

            if ($account !== null) {
                $sync->handle($account);
            }
        });
    }
}
