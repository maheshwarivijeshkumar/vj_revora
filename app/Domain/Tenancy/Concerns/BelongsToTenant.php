<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Concerns;

use App\Domain\Tenancy\Exceptions\MissingTenantContextException;
use App\Domain\Tenancy\Scopes\TenantScope;
use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as tenant-owned.
 *
 * Applied to every model whose table carries `tenant_id`. The schema test in
 * tests/Feature/Tenancy/TenantIsolationTest.php fails the build when a
 * tenant-owned table exists without this trait on its model, so the two cannot
 * drift apart.
 *
 * @property int $tenant_id
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('tenant_id') !== null) {
                return;
            }

            $tenantId = app(TenantContext::class)->id();

            if ($tenantId === null) {
                // Refuse rather than writing an orphaned row. A record with a
                // null tenant_id is invisible to every scoped query and is
                // effectively lost data, so failing loudly here is cheaper
                // than discovering it later.
                throw MissingTenantContextException::forModel($model::class);
            }

            $model->setAttribute('tenant_id', $tenantId);
        });
    }

    /**
     * Guards against a tenant_id being reassigned after creation, which would
     * silently move a record between tenants.
     */
    public function initializeBelongsToTenant(): void
    {
        $this->mergeGuarded(['tenant_id']);
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
