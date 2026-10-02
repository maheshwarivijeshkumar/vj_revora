<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlatformUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * Platform staff: owner, super admin, billing/integration/support admins.
 *
 * A separate model and guard from User by design — platform staff are never
 * tenant-scoped, and keeping them out of the tenant users table means no
 * single column separates a customer from a super admin.
 */
final class PlatformUser extends Authenticatable
{
    /** @use HasFactory<PlatformUserFactory> */
    use HasFactory, Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected static function booted(): void
    {
        self::creating(function (self $user): void {
            $user->uuid ??= (string) Str::uuid7();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
