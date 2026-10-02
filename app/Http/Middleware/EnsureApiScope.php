<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Api\ApiScope;
use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level scope gate: `->middleware('scope:leads.write')` (§48).
 *
 * Kept out of controllers so the access a route needs is declared where the
 * route is, and is visible in `route:list`.
 */
final class EnsureApiScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $key = $request->attributes->get('api_key');
        $required = ApiScope::tryFrom($scope);

        if (! $key instanceof ApiKey || $required === null) {
            return response()->json(
                ['message' => 'This endpoint requires an API key.'],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        if (! $key->allows($required)) {
            // Naming the missing scope turns a dead end into something the
            // caller can act on (§59).
            return response()->json([
                'message' => sprintf('This API key does not have the "%s" scope.', $scope),
                'required_scope' => $scope,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
