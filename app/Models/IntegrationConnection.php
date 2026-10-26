<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Integrations\Enums\LeadAdsPlatform;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\IntegrationConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One authorized lead-ad account: a Facebook Page or a LinkedIn ad account (§10).
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property LeadAdsPlatform $provider
 * @property string $account_id
 * @property string $account_name
 * @property ConnectionStatus $status
 * @property string $access_token
 * @property Carbon|null $token_expires_at
 * @property array<string, mixed>|null $settings
 * @property int|null $lead_source_id
 * @property int|null $connected_by
 * @property Carbon|null $last_synced_at
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class IntegrationConnection extends Model
{
    /** @use HasFactory<IntegrationConnectionFactory> */
    use BelongsToTenant, HasFactory;

    protected $guarded = [];

    /** Tokens never leave the server (§11). */
    protected $hidden = ['access_token'];

    protected static function booted(): void
    {
        self::creating(function (self $connection): void {
            $connection->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<LeadSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'lead_source_id');
    }

    /** @return HasMany<IntegrationSyncRun, $this> */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(IntegrationSyncRun::class);
    }

    public function tokenHasExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => LeadAdsPlatform::class,
            'status' => ConnectionStatus::class,
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'settings' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }
}
