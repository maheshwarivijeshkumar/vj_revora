<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Billing\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level quota gate: `->middleware('limit:leads_per_month')`.
 */
final class EnsureWithinLimit
{
    public function __construct(
        private readonly Entitlements $entitlements,
    ) {}

    public function handle(Request $request, Closure $next, string $meter, int $quantity = 1): Response
    {
        if (! $this->entitlements->withinLimit($meter, $quantity)) {
            abort(
                Response::HTTP_PAYMENT_REQUIRED,
                'You have reached your plan limit for this action.',
            );
        }

        return $next($request);
    }
}
