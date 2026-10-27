<?php

declare(strict_types=1);

namespace App\Domain\Opportunities\Actions;

use App\Domain\Tenancy\TenantContext;
use App\Models\ServiceProfile;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Re-scores opportunities for every active workspace that has set up what it
 * sells. Workspaces without service profiles are skipped: there is nothing to
 * match against.
 */
final readonly class MatchAllWorkspaces
{
    public function __construct(
        private TenantContext $context,
        private MatchOpportunities $match,
    ) {}

    /**
     * @return int new matches across all workspaces
     */
    public function handle(): int
    {
        /** @var Collection<int, int> $tenantIds */
        $tenantIds = $this->context->withoutScoping(
            fn (): Collection => ServiceProfile::query()->distinct()->pluck('tenant_id'),
        );

        $tenants = $this->context->withoutScoping(fn (): Collection => Tenant::query()->whereIn('id', $tenantIds)->get());
        $created = 0;

        foreach ($tenants as $tenant) {
            if ($tenant->isActive()) {
                $created += $this->context->runAs($tenant, fn (): int => $this->match->handle());
            }
        }

        return $created;
    }
}
