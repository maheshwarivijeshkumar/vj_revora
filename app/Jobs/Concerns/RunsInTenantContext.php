<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use Closure;
use RuntimeException;

/**
 * Restores the workspace a job was queued for (ADR-009, ADR-010).
 *
 * A queue worker is a long-lived process shared by every tenant, so nothing
 * about the request that queued the job survives into `handle()`. Without this,
 * the global scope fails closed and the job quietly finds nothing — which is
 * the safe failure, but still a failure.
 *
 * The id is carried on the job rather than the Tenant model: a serialised model
 * would be reloaded through the same scope that is not bound yet.
 */
trait RunsInTenantContext
{
    public int $tenantId;

    /**
     * Binds the workspace for the duration of the callback.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function inTenantContext(Closure $callback): mixed
    {
        $context = app(TenantContext::class);

        $tenant = $context->withoutScoping(
            fn (): ?Tenant => Tenant::query()->find($this->tenantId),
        );

        if (! $tenant instanceof Tenant) {
            // Deleted between queueing and running. Nothing to do, and nothing
            // a retry would fix, so the caller is told rather than left with a
            // job that silently did nothing.
            throw new RuntimeException("Workspace [{$this->tenantId}] no longer exists.");
        }

        return $context->runAs($tenant, $callback);
    }

    /**
     * Remembers the workspace this job belongs to. Called at construction.
     */
    protected function captureTenant(): void
    {
        $this->tenantId = app(TenantContext::class)->tenantOrFail()->id;
    }
}
