<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Messaging\Enums\MailboxProvider;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\EmailAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A mailbox the CRM sends from and reads replies out of (§25).
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property MailboxProvider $provider
 * @property string $email
 * @property string|null $from_name
 * @property ConnectionStatus $status
 * @property array<string, mixed> $credentials
 * @property bool $is_default
 * @property Carbon|null $last_synced_at
 * @property string|null $last_error
 * @property int|null $connected_by
 */
final class EmailAccount extends Model
{
    /** @use HasFactory<EmailAccountFactory> */
    use BelongsToTenant, HasFactory;

    protected $guarded = [];

    /** Passwords and tokens never leave the server (§11). */
    protected $hidden = ['credentials'];

    protected static function booted(): void
    {
        self::creating(function (self $account): void {
            $account->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * The mailbox follow-ups go out from: the default one, or failing that
     * the first that still works.
     */
    public static function sender(): ?self
    {
        return self::query()
            ->where('status', '!=', ConnectionStatus::NeedsReconnect->value)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => MailboxProvider::class,
            'status' => ConnectionStatus::class,
            'credentials' => 'encrypted:array',
            'is_default' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }
}
