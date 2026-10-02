<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Models\ApiRequest;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs API traffic and meters it against the plan (§9, §51, §74).
 *
 * Runs as a terminable middleware so the write happens after the response has
 * been sent: metering must never add latency to the call it is measuring.
 */
final class RecordApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('api_started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $key = $request->attributes->get('api_key');

        if (! $key instanceof ApiKey) {
            return;
        }

        $startedAt = $request->attributes->get('api_started_at');

        // forceCreate because BelongsToTenant guards tenant_id against mass
        // assignment. The entry belongs to the key's workspace, which is known
        // explicitly here and must not depend on a context that may already
        // have been torn down by the time terminate() runs.
        ApiRequest::forceCreate([
            'tenant_id' => $key->tenant_id,
            'api_key_id' => $key->id,
            'method' => $request->method(),
            // The pattern rather than the resolved path, so a million calls to
            // /v1/leads/{id} group into one row in the usage report instead of
            // a million distinct paths.
            'path' => $request->route()?->uri() ?? $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => is_float($startedAt)
                ? (int) round((microtime(true) - $startedAt) * 1000)
                : null,
            'ip' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
