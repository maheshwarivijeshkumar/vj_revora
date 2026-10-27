<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The workspace's Upwork connection, used only to read job postings.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string|null $name
 * @property array{access_token: string, refresh_token?: string|null, expires_at?: int} $credentials
 * @property string|null $organization_id
 * @property ConnectionStatus $status
 * @property string|null $last_error
 * @property Carbon|null $last_synced_at
 */
final class UpworkAccount extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $hidden = ['credentials'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'status' => ConnectionStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }
}
