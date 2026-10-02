<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Exceptions\MissingTenantContextException;
use App\Domain\Tenancy\Scopes\TenantScope;
use App\Models\Tenant;
use Closure;

/**
 * Holds the tenant that the current request, job or command is acting for.
 *
 * Registered as a singleton. Everything tenant-scoped reads from here —
 * the Eloquent global scope, the entitlement service, queued jobs and the
 * audit trail — so there is exactly one answer to "whose data is this?".
 *
 * @see TenantScope
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    /**
     * Depth counter rather than a boolean, so nested withoutScoping() calls
     * do not have the inner one re-enable scoping on the way out.
     */
    private int $unscopedDepth = 0;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->getKey();
    }

    public function check(): bool
    {
        return $this->tenant instanceof Tenant;
    }

    /**
     * @throws MissingTenantContextException when no tenant is bound.
     */
    public function tenantOrFail(): Tenant
    {
        return $this->tenant ?? throw MissingTenantContextException::make();
    }

    public function isUnscoped(): bool
    {
        return $this->unscopedDepth > 0;
    }

    /**
     * Runs a callback with tenant scoping disabled.
     *
     * This is the *only* sanctioned way to query across tenants, and it exists
     * for platform administration, cross-tenant reporting and maintenance
     * commands. Calling it inside a tenant-facing request is a bug — the
     * narrow, explicit call site is the point.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function withoutScoping(Closure $callback): mixed
    {
        $this->unscopedDepth++;

        try {
            return $callback();
        } finally {
            $this->unscopedDepth--;
        }
    }

    /**
     * Runs a callback as a given tenant, restoring the previous tenant after.
     *
     * Used by queued jobs restoring their serialized tenant, and by platform
     * admin acting on a specific tenant.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function runAs(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
