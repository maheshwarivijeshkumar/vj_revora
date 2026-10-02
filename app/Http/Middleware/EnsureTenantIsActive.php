<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks requests for tenants that are suspended, cancelled or still
 * provisioning.
 */
final class EnsureTenantIsActive
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->get();

        if ($tenant === null) {
            abort(Response::HTTP_NOT_FOUND, 'Workspace not found.');
        }

        if (! $tenant->status->permitsAccess()) {
            abort(Response::HTTP_FORBIDDEN, 'This workspace is not active.');
        }

        return $next($request);
    }
}
