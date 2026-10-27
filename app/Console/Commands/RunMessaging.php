<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Messaging\Enums\EnrollmentStatus;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Settings\SetupController;
use App\Jobs\RunDueFollowUps;
use App\Jobs\SyncMailboxJob;
use App\Models\EmailAccount;
use App\Models\FollowUpEnrollment;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Queues the messaging work across every workspace: due follow-up emails,
 * and (with --mailboxes) reading inboxes for replies.
 */
final class RunMessaging extends Command
{
    protected $signature = 'messaging:run {--mailboxes : Read connected inboxes for replies instead of sending follow-ups}';

    protected $description = 'Queue due follow-up emails, or read connected mailboxes for lead replies';

    public function handle(TenantContext $context): int
    {
        // Proof for the setup checklist that the scheduler is alive.
        Cache::put(SetupController::HEARTBEAT_KEY, now()->toIso8601String(), now()->addMinutes(5));

        return $this->option('mailboxes')
            ? $this->syncMailboxes($context)
            : $this->runFollowUps($context);
    }

    private function runFollowUps(TenantContext $context): int
    {
        /** @var Collection<int, int> $tenantIds */
        $tenantIds = $context->withoutScoping(fn (): Collection => FollowUpEnrollment::query()
            ->where('status', EnrollmentStatus::Active->value)
            ->where('next_run_at', '<=', now())
            ->distinct()
            ->pluck('tenant_id'));

        foreach ($this->activeTenants($context, $tenantIds) as $tenant) {
            $context->runAs($tenant, fn () => RunDueFollowUps::dispatch());
        }

        return self::SUCCESS;
    }

    private function syncMailboxes(TenantContext $context): int
    {
        /** @var Collection<int, EmailAccount> $accounts */
        $accounts = $context->withoutScoping(fn (): Collection => EmailAccount::query()
            ->where('status', '!=', ConnectionStatus::NeedsReconnect->value)
            ->get(['id', 'tenant_id']));

        $tenants = $this->activeTenants($context, $accounts->pluck('tenant_id'))->keyBy('id');

        foreach ($accounts as $account) {
            $tenant = $tenants->get($account->tenant_id);

            if ($tenant instanceof Tenant) {
                $context->runAs($tenant, fn () => SyncMailboxJob::dispatch($account->id));
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, Tenant>
     */
    private function activeTenants(TenantContext $context, Collection $ids): Collection
    {
        return $context->withoutScoping(fn (): Collection => Tenant::query()
            ->whereIn('id', $ids->unique()->values())
            ->get()
            ->filter(fn (Tenant $tenant): bool => $tenant->isActive())
            ->values());
    }
}
