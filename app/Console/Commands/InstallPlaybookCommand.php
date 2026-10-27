<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Opportunities\Actions\InstallPlaybook;
use App\Domain\Opportunities\Actions\MatchOpportunities;
use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Sets a workspace up with the Berg Media Digital and Vijesh service
 * profiles and the email playbook, then scores the opportunities already in.
 * The same as "Set up profiles" on Settings → Services.
 */
final class InstallPlaybookCommand extends Command
{
    protected $signature = 'crm:install-playbook {tenant : Workspace id or slug}';

    protected $description = 'Install the service profiles and email playbook for a workspace';

    public function handle(TenantContext $context, InstallPlaybook $playbook, MatchOpportunities $match): int
    {
        $key = (string) $this->argument('tenant');

        $tenant = $context->withoutScoping(fn (): ?Tenant => Tenant::query()
            ->when(ctype_digit($key), fn ($q) => $q->whereKey((int) $key), fn ($q) => $q->where('slug', $key))
            ->first());

        if ($tenant === null) {
            $this->error("No workspace [{$key}].");

            return self::FAILURE;
        }

        [$templates, $matches] = $context->runAs($tenant, fn (): array => [$playbook->handle(), $match->handle()]);

        $this->info("{$tenant->name}: profiles ready, {$templates} templates added, {$matches} opportunities matched.");

        return self::SUCCESS;
    }
}
