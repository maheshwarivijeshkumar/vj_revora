<?php

declare(strict_types=1);

namespace App\Domain\Billing;

use App\Domain\Tenancy\TenantContext;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\UsageMeter;
use Illuminate\Support\Facades\Cache;

/**
 * The single place subscription entitlements are evaluated (§93).
 *
 * Plan checks must never be scattered through controllers. Everything asks
 * here: route middleware, domain actions, the Inertia share that drives the
 * UI, and the API layer.
 *
 * No plan key, price or limit is hard-coded — all of it is read from
 * plan_features, so changing a limit is a data change (§101.28).
 */
final readonly class Entitlements
{
    private const CACHE_TTL_MINUTES = 5;

    public function __construct(
        private TenantContext $context,
    ) {}

    /**
     * Whether the tenant's plan grants a feature at all.
     */
    public function hasFeature(string $key, ?Tenant $tenant = null): bool
    {
        return $this->grants($tenant)[$key]['enabled'] ?? false;
    }

    /**
     * The numeric cap for a metered feature, or null when unlimited or ungranted.
     */
    public function limit(string $key, ?Tenant $tenant = null): ?int
    {
        return $this->grants($tenant)[$key]['limit'] ?? null;
    }

    /**
     * Current consumption of a meter in the active billing period.
     */
    public function used(string $key, ?Tenant $tenant = null): int
    {
        $tenantId = $tenant?->getKey() ?? $this->context->id();

        if ($tenantId === null) {
            return 0;
        }

        return (int) UsageMeter::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('meter_key', $key)
            ->where('period_start', '<=', now())
            ->where('period_end', '>=', now())
            ->value('used');
    }

    /**
     * Headroom left on a meter. Null means unlimited.
     */
    public function remaining(string $key, ?Tenant $tenant = null): ?int
    {
        $limit = $this->limit($key, $tenant);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->used($key, $tenant));
    }

    /**
     * Whether consuming $quantity more would stay inside the plan limit.
     *
     * Callers check this *before* doing the work, not after — an over-limit
     * message that has already left the building cannot be un-sent.
     */
    public function withinLimit(string $key, int $quantity = 1, ?Tenant $tenant = null): bool
    {
        if (! $this->hasFeature($key, $tenant)) {
            return false;
        }

        $remaining = $this->remaining($key, $tenant);

        return $remaining === null || $remaining >= $quantity;
    }

    /**
     * Whether usage has crossed a warning threshold, used to surface
     * "your quota is almost exhausted" before it actually is.
     */
    public function nearingLimit(string $key, float $threshold = 0.8, ?Tenant $tenant = null): bool
    {
        $limit = $this->limit($key, $tenant);

        if ($limit === null || $limit === 0) {
            return false;
        }

        return ($this->used($key, $tenant) / $limit) >= $threshold;
    }

    /**
     * Every feature's state for the tenant, shared with Inertia so the UI
     * hides and disables consistently with what the server would allow.
     *
     * @return array<string, array{enabled: bool, limit: int|null, used: int, remaining: int|null}>
     */
    public function snapshot(?Tenant $tenant = null): array
    {
        $snapshot = [];

        foreach ($this->grants($tenant) as $key => $grant) {
            $used = $this->used($key, $tenant);
            $limit = $grant['limit'];

            $snapshot[$key] = [
                'enabled' => $grant['enabled'],
                'limit' => $limit,
                'used' => $used,
                'remaining' => $limit === null ? null : max(0, $limit - $used),
            ];
        }

        return $snapshot;
    }

    /**
     * The tenant's feature grants, as plain data.
     *
     * Deliberately caches an array rather than the Plan model: serialising
     * Eloquent models into a shared cache is fragile — they come back as
     * __PHP_Incomplete_Class across deploys and carry stale relations. Plain
     * arrays are also cheaper to hydrate on every request.
     *
     * @return array<string, array{enabled: bool, limit: int|null}>
     */
    private function grants(?Tenant $tenant): array
    {
        $tenantId = $tenant?->getKey() ?? $this->context->id();

        if ($tenantId === null) {
            return [];
        }

        return Cache::remember(
            self::cacheKey($tenantId),
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            function () use ($tenantId): array {
                $subscription = Subscription::query()
                    ->with('plan.features')
                    ->where('tenant_id', $tenantId)
                    ->latest('id')
                    ->first();

                // A cancelled or expired subscription grants nothing. A
                // scheduled downgrade does not apply until the period rolls
                // over, so the current plan is still the one in force.
                if ($subscription === null || ! $subscription->isUsable()) {
                    return [];
                }

                $grants = [];

                foreach ($subscription->plan->features as $feature) {
                    $grants[$feature->feature_key] = [
                        'enabled' => $feature->isEnabled(),
                        'limit' => $feature->limit(),
                    ];
                }

                return $grants;
            },
        );
    }

    /**
     * Drops the cached grants for a tenant. Called on any subscription change.
     */
    public static function flush(Tenant $tenant): void
    {
        Cache::forget(self::cacheKey($tenant->getKey()));
    }

    private static function cacheKey(int $tenantId): string
    {
        return "entitlements:grants:{$tenantId}";
    }
}
