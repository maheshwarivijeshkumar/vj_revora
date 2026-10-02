<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Api\ApiScope;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A tenant API credential (§48).
 *
 * The secret is shown once at creation and never again. Only a lookup prefix
 * and a hash are stored, so a lost key is rotated rather than recovered —
 * which is the point, not an inconvenience.
 *
 * @property int $id
 * @property string $name
 * @property string $prefix
 * @property string $hash
 * @property list<string> $scopes
 * @property int|null $rate_limit
 * @property CarbonInterface|null $expires_at
 * @property CarbonInterface|null $revoked_at
 * @property CarbonInterface|null $last_used_at
 */
final class ApiKey extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $hidden = ['hash'];

    /** Identifies our keys in logs and support tickets at a glance. */
    private const TOKEN_PREFIX = 'rvk';

    protected static function booted(): void
    {
        self::creating(function (self $key): void {
            $key->uuid ??= (string) Str::uuid7();
        });
    }

    /**
     * Mints a key and returns the one and only copy of its secret.
     *
     * @param  list<string>  $scopes
     * @return array{key: self, token: string}
     */
    public static function mint(string $name, array $scopes, ?int $createdBy = null, ?CarbonInterface $expiresAt = null): array
    {
        // The prefix is stored in clear so a key can be looked up in one
        // indexed query. Hashing every stored key and comparing against all of
        // them would mean a full table scan per request.
        $prefix = Str::lower(Str::random(12));
        $secret = Str::random(40);

        $key = self::create([
            'name' => $name,
            'prefix' => $prefix,
            'hash' => Hash::make($secret),
            'scopes' => $scopes,
            'created_by' => $createdBy,
            'expires_at' => $expiresAt,
        ]);

        return [
            'key' => $key,
            'token' => self::TOKEN_PREFIX.'_'.$prefix.'_'.$secret,
        ];
    }

    /**
     * Splits a presented token into its prefix and secret.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function parse(string $token): ?array
    {
        $parts = explode('_', $token, 3);

        if (count($parts) !== 3 || $parts[0] !== self::TOKEN_PREFIX) {
            return null;
        }

        [, $prefix, $secret] = $parts;

        return $prefix === '' || $secret === '' ? null : [$prefix, $secret];
    }

    public function matches(string $secret): bool
    {
        return Hash::check($secret, $this->hash);
    }

    /**
     * Whether the key may still be used at all.
     *
     * Revocation and expiry are separate: a revoked key was withdrawn on
     * purpose, an expired one simply aged out, and support needs to tell them
     * apart when someone asks why a key stopped working.
     */
    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /** Whether this key grants a scope, directly or by implication. */
    public function allows(ApiScope $scope): bool
    {
        foreach ($this->scopes as $granted) {
            $candidate = ApiScope::tryFrom($granted);

            if ($candidate?->implies($scope)) {
                return true;
            }
        }

        return false;
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scopes' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
}
