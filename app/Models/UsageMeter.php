<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Rolled-up consumption of one meter over one billing period (§9). */
final class UsageMeter extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function isExhausted(): bool
    {
        return ! $this->is_unlimited
            && $this->limit !== null
            && $this->used >= $this->limit;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'is_unlimited' => 'boolean',
            'overage_allowed' => 'boolean',
        ];
    }
}
