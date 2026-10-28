<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\ClientStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A customer created from a won lead, or entered directly.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $lead_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $company_name
 * @property ClientStatus $status
 * @property string|null $notes
 * @property CarbonInterface|null $converted_at
 */
final class Client extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<Estimate, $this> */
    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class);
    }

    /** @return HasMany<Invoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ClientStatus::class,
            'converted_at' => 'datetime',
        ];
    }
}
