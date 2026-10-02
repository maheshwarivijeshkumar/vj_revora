<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A pre-launch waitlist signup. Central record — captured before any tenant
 * exists, so deliberately not tenant-scoped.
 *
 * @property string $email
 * @property string $email_normalized
 */
final class WaitlistSignup extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $signup): void {
            $signup->uuid ??= (string) Str::uuid7();
            // Normalised separately from the display value so the unique index
            // actually collapses "Me@Example.com " and "me@example.com".
            $signup->email_normalized = Str::lower(trim($signup->email));
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'utm' => 'array',
            'consent' => 'boolean',
            'consent_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }
}
