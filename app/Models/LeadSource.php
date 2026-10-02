<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Leads\Enums\LeadSourceType;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\LeadSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Where a workspace's leads come from (§2, §11).
 *
 * @property string $key
 * @property LeadSourceType $type
 */
final class LeadSource extends Model
{
    /** @use HasFactory<LeadSourceFactory> */
    use BelongsToTenant, HasFactory;

    protected $guarded = [];

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    /** Whether leads here arrived through a provider API we may read (§2). */
    public function isAuthorizedApi(): bool
    {
        return $this->type->isAuthorizedApi();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LeadSourceType::class,
            'is_active' => 'boolean',
            'config' => 'array',
        ];
    }
}
