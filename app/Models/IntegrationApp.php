<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A workspace's own app credentials for one network.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $provider
 * @property bool $enabled
 * @property string|null $client_id
 * @property string|null $client_secret
 * @property string|null $api_key
 */
final class IntegrationApp extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'enabled' => false,
    ];

    /** Secrets never leave the server. */
    protected $hidden = ['client_secret', 'api_key'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'client_secret' => 'encrypted',
            'api_key' => 'encrypted',
        ];
    }
}
