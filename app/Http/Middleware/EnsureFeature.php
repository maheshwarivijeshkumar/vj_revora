<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Billing\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level entitlement gate: `->middleware('feature:automation')`.
 *
 * Exists so plan checks stay out of controllers (§93).
 */
final class EnsureFeature
{
    public function __construct(
        private readonly Entitlements $entitlements,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $this->entitlements->hasFeature($feature)) {
            abort(
                Response::HTTP_PAYMENT_REQUIRED,
                'Your plan does not include this feature.',
            );
        }

        return $next($request);
    }
}
