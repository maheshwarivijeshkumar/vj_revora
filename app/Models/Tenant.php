<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Enums\TenantStatus;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * A customer workspace. Central record — deliberately NOT tenant-scoped.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property TenantStatus $status
 */
final class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $tenant): void {
            $tenant->uuid ??= (string) Str::uuid7();
        });
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<TenantDomain, $this> */
    public function domains(): HasMany
    {
        return $this->hasMany(TenantDomain::class);
    }

    /** @return HasOne<Subscription, $this> */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'data' => 'array',
            'trial_ends_at' => 'datetime',
            'provisioned_at' => 'datetime',
        ];
    }
}
