<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\AdCampaignFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An advertising campaign this workspace can name.
 *
 * Live rows come from a connected Facebook Page (which also covers Instagram)
 * or a LinkedIn ad account. Google, TikTok and any other network appear when
 * a lead already attributes itself to that campaign.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $integration_connection_id
 * @property string $platform
 * @property string $external_id
 * @property string $name
 * @property string|null $status
 * @property string|null $objective
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon $last_seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class AdCampaign extends Model
{
    /** @use HasFactory<AdCampaignFactory> */
    use BelongsToTenant, HasFactory;

    protected $guarded = [];

    /** @return BelongsTo<IntegrationConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(IntegrationConnection::class, 'integration_connection_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
