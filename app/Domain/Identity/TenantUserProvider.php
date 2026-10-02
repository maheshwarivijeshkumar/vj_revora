<?php

declare(strict_types=1);

namespace App\Domain\Identity;

use App\Domain\Tenancy\Scopes\TenantScope;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Authenticates users across the tenant boundary.
 *
 * Authentication is the one operation that cannot be tenant-scoped up front:
 * at sign-in there is no bound tenant yet, and it is the *user* who determines
 * which tenant the session belongs to. With TenantScope applied, every lookup
 * would fail closed and nobody could ever sign in.
 *
 * So the global scope is lifted here — narrowly, for credential lookup only —
 * and the tenant is then bound from the authenticated user by ResolveTenant.
 *
 * Because users.email is unique *per tenant* rather than globally, one address
 * may legitimately exist in several workspaces. Where the request already
 * identifies a tenant (custom domain or subdomain) the lookup is constrained
 * to it. Where it does not and the address is ambiguous, authentication is
 * refused rather than guessing which workspace was meant.
 */
final class TenantUserProvider extends EloquentUserProvider
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $credentials = array_filter(
            $credentials,
            fn (string $key): bool => ! str_contains($key, 'password'),
            ARRAY_FILTER_USE_KEY,
        );

        if ($credentials === []) {
            return null;
        }

        $query = $this->unscopedQuery();

        foreach ($credentials as $key => $value) {
            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        $tenantId = app(TenantContext::class)->id();

        if ($tenantId !== null) {
            return $query->where('tenant_id', $tenantId)->first();
        }

        // No tenant identified by the request: accept only an unambiguous
        // match. Two workspaces sharing an address must sign in through their
        // own domain.
        $matches = $query->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->unscopedQuery()
            ->where($this->createModel()->getAuthIdentifierName(), $identifier)
            ->first();
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        $model = $this->createModel();

        $user = $this->unscopedQuery()
            ->where($model->getAuthIdentifierName(), $identifier)
            ->first();

        if ($user === null) {
            return null;
        }

        $rememberToken = $user->getRememberToken();

        return $rememberToken !== null && hash_equals($rememberToken, $token)
            ? $user
            : null;
    }

    /**
     * A query for the configured auth model with tenant scoping lifted.
     *
     * Typed to the model contract rather than to User, because the concrete
     * class comes from `auth.providers.users.model` and this provider does not
     * assume which one it is.
     *
     * @return Builder<Authenticatable&Model>
     */
    private function unscopedQuery(): Builder
    {
        return $this->createModel()->newQuery()->withoutGlobalScope(TenantScope::class);
    }
}
