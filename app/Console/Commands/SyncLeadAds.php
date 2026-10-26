<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Tenancy\TenantContext;
use App\Jobs\SyncLeadAdsConnectionJob;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncRun;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Queues a lead sync for every connected lead-ad account (§57).
 *
 * Scheduled hourly. Runs across every workspace, binding each one in turn so
 * the queued job carries the right tenant.
 */
final class SyncLeadAds extends Command
{
    protected $signature = 'leads:sync-ads';

    protected $description = 'Queue a lead sync for every connected Facebook, Instagram and LinkedIn lead-ad account';

    public function handle(TenantContext $context): int
    {
        /** @var Collection<int, IntegrationConnection> $connections */
        $connections = $context->withoutScoping(fn (): Collection => IntegrationConnection::query()
            ->where('status', '!=', 'needs_reconnect')
            ->get(['id', 'tenant_id']));

        $tenants = $context->withoutScoping(fn (): Collection => Tenant::query()
            ->whereIn('id', $connections->pluck('tenant_id')->unique())
            ->get()
            ->keyBy('id'));

        $queued = 0;

        foreach ($connections as $connection) {
            $tenant = $tenants->get($connection->tenant_id);

            // A suspended or cancelled workspace keeps its connections but
            // stops pulling leads it cannot use.
            if (! $tenant instanceof Tenant || ! $tenant->isActive()) {
                continue;
            }

            $context->runAs($tenant, fn () => SyncLeadAdsConnectionJob::dispatch(
                $connection->id,
                IntegrationSyncRun::TRIGGER_SCHEDULE,
            ));

            $queued++;
        }

        $this->info("Queued {$queued} lead-ad sync(s).");

        return self::SUCCESS;
    }
}
