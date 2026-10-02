<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A public contact-form enquiry. Central record — submitted before any
 * workspace exists, so deliberately not tenant-scoped.
 */
final class ContactEnquiry extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $enquiry): void {
            $enquiry->uuid ??= (string) Str::uuid7();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consent' => 'boolean',
            'consent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }
}
