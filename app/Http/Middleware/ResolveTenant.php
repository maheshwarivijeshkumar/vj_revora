<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Models\TenantDomain;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the tenant for the current request.
 *
 * Resolution order: the authenticated user's tenant first, then the request
 * hostname. The user is authoritative — a signed-in user must never be served
 * another tenant's data because of a hostname mismatch.
 */
final class ResolveTenant
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolve($request);

        if ($tenant instanceof Tenant) {
            $this->context->set($tenant);

            // Roles are tenant-scoped via Spatie's team mode, keyed to
            // tenant_id. Without this the registrar has no team bound and
            // every permission check silently returns false — the user would
            // appear to have no permissions at all.
            app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->getKey());
        }

        return $next($request);
    }

    private function resolve(Request $request): ?Tenant
    {
        $user = $request->user();

        // Queried by key rather than through the relation so the return type
        // is a Tenant, not a generic Model.
        if ($user !== null && isset($user->tenant_id)) {
            return Tenant::query()->find($user->tenant_id);
        }

        return TenantDomain::query()
            ->where('domain', $request->getHost())
            ->first()?->tenant;
    }
}
