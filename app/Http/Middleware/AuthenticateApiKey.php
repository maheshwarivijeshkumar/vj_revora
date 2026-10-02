<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\TenantContext;
use App\Models\ApiKey;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a request by API key (§48).
 *
 * Runs before anything tenant-scoped, because the key is what identifies the
 * workspace. With no tenant bound the global scope fails closed, so an
 * unauthenticated API request sees nothing rather than everything.
 */
final class AuthenticateApiKey
{
    public function __construct(
        private readonly TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->token($request);

        if ($token === null) {
            return $this->unauthorised('Provide an API key as a bearer token.');
        }

        $parsed = ApiKey::parse($token);

        if ($parsed === null) {
            return $this->unauthorised('That API key is not in a recognised format.');
        }

        [$prefix, $secret] = $parsed;

        // Looked up unscoped: the key is what tells us which workspace this
        // is, so there is nothing to scope by yet.
        $key = $this->context->withoutScoping(
            fn (): ?ApiKey => ApiKey::query()->where('prefix', $prefix)->first(),
        );

        // Same message and timing path whether the prefix was unknown or the
        // secret was wrong, so responses cannot be used to confirm that a key
        // exists.
        if ($key === null || ! $key->matches($secret)) {
            return $this->unauthorised('That API key is not valid.');
        }

        if (! $key->isUsable()) {
            return $this->forbidden(
                $key->revoked_at !== null
                    ? 'That API key has been revoked.'
                    : 'That API key has expired.',
            );
        }

        $tenant = $this->context->withoutScoping(
            fn (): ?Tenant => Tenant::query()->find($key->tenant_id),
        );

        if ($tenant === null || ! $tenant->status->permitsAccess()) {
            return $this->forbidden('This workspace is not active.');
        }

        $this->context->set($tenant);
        $request->attributes->set('api_key', $key);

        // Written without touching updated_at, so "last used" does not make
        // every request a write that invalidates caches.
        $key->forceFill(['last_used_at' => now()])->saveQuietly();

        return $next($request);
    }

    /**
     * Reads the key from the Authorization header, or the X-Api-Key header
     * that several integration platforms send instead.
     */
    private function token(Request $request): ?string
    {
        $bearer = $request->bearerToken();

        if (is_string($bearer) && $bearer !== '') {
            return $bearer;
        }

        $header = $request->header('X-Api-Key');

        return is_string($header) && $header !== '' ? $header : null;
    }

    private function unauthorised(string $message): JsonResponse
    {
        return response()->json(
            ['message' => $message],
            Response::HTTP_UNAUTHORIZED,
            ['WWW-Authenticate' => 'Bearer'],
        );
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
    }
}
