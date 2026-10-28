<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\ProjectStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Work being delivered for a client.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $client_id
 * @property string $name
 * @property ProjectStatus $status
 * @property string|null $description
 * @property CarbonInterface|null $starts_on
 * @property CarbonInterface|null $ends_on
 */
final class Project extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return HasMany<ChangeOrder, $this> */
    public function changeOrders(): HasMany
    {
        return $this->hasMany(ChangeOrder::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
