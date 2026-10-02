<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\Entitlements;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Current plan usage (§9, §47).
 *
 * Exposed so an integration can back off before it hits a limit rather than
 * discovering one through a 402 mid-run.
 */
final class UsageController extends Controller
{
    public function __invoke(Entitlements $entitlements, TenantContext $context): JsonResponse
    {
        $tenant = $context->get();

        return response()->json([
            'data' => [
                'workspace' => $tenant?->name,
                'features' => $entitlements->snapshot($tenant),
            ],
        ]);
    }
}
