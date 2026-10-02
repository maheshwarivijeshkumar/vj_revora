<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscription plan. Central record.
 *
 * Plans are data. No plan key, price or limit may be branched on in
 * application code (§8, §101.28) — ask the entitlement service instead.
 */
final class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $guarded = [];

    /** @return HasMany<PlanFeature, $this> */
    public function features(): HasMany
    {
        return $this->hasMany(PlanFeature::class);
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function feature(string $key): ?PlanFeature
    {
        return $this->features->firstWhere('feature_key', $key);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
