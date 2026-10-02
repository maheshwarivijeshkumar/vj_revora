<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Scopes;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Constrains every query on a tenant-owned model to the current tenant.
 *
 * Layer 1 of the three isolation layers described in docs/plan/02-DATA-MODEL.md.
 *
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isUnscoped()) {
            return;
        }

        $tenantId = $context->id();

        if ($tenantId === null) {
            // Fail closed. With no tenant bound we cannot know what this query
            // is entitled to see, so it sees nothing. The alternative —
            // returning unscoped rows — turns every missing-context bug into a
            // cross-tenant data leak, which is the one failure mode that must
            // never be silent. Legitimate cross-tenant access goes through
            // TenantContext::withoutScoping().
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
