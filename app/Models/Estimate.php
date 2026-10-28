<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\EstimateStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A price offered to a client before any invoice exists.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $client_id
 * @property int|null $project_id
 * @property string $number
 * @property string $title
 * @property EstimateStatus $status
 * @property list<array{description: string, quantity: string, unit_price: string, amount: string}> $lines
 * @property string $tax_rate
 * @property string $subtotal
 * @property string $tax
 * @property string $total
 * @property CarbonInterface|null $valid_until
 * @property string|null $notes
 */
final class Estimate extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasOne<Invoice, $this> */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EstimateStatus::class,
            'lines' => 'array',
            'tax_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'valid_until' => 'date',
        ];
    }
}
