<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Campaigns\Actions\SyncCampaigns as SyncCampaignsAction;
use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Names every campaign a workspace can see.
 *
 * Scheduled hourly beside the lead-ad sync. Each workspace is bound in turn
 * so the tenant scope stays on the rows it writes.
 */
final class SyncCampaigns extends Command
{
    protected $signature = 'campaigns:sync';

    protected $description = 'Fetch ad campaigns from connected accounts and from leads already in the CRM';

    public function handle(TenantContext $context, SyncCampaignsAction $sync): int
    {
        $saved = 0;

        foreach (Tenant::query()->get() as $tenant) {
            if (! $tenant->isActive()) {
                continue;
            }

            $saved += $context->runAs($tenant, fn (): int => $sync->handle());
        }

        $this->info("Updated {$saved} campaign(s).");

        return self::SUCCESS;
    }
}
