<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\ChangeOrderStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A priced change against a project after the original estimate.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $project_id
 * @property string $number
 * @property string $title
 * @property ChangeOrderStatus $status
 * @property string $amount
 * @property string|null $reason
 */
final class ChangeOrder extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChangeOrderStatus::class,
            'amount' => 'decimal:2',
        ];
    }
}
