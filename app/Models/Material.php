<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Stock or a priced material used on jobs.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $unit
 * @property string $quantity
 * @property string $unit_cost
 * @property string|null $supplier
 */
final class Material extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_cost' => 'decimal:2',
        ];
    }
}
