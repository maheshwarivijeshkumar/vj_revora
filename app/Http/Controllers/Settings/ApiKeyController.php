<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Domain\Api\ApiScope;
use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\ApiRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * API key management (§48, §50).
 */
final class ApiKeyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/ApiKeys', [
            'keys' => ApiKey::query()
                ->with('creator:id,name')
                ->orderByDesc('id')
                ->get()
                ->map(fn (ApiKey $key): array => [
                    'id' => $key->id,
                    'name' => $key->name,
                    // The prefix is all that can be shown. It is enough to
                    // identify a key in a log without being usable.
                    'prefix' => $key->prefix,
                    'scopes' => $key->scopes,
                    'created_by' => $key->creator?->name,
                    'created_at' => $key->created_at?->toIso8601String(),
                    'last_used_at' => $key->last_used_at?->toIso8601String(),
                    'expires_at' => $key->expires_at?->toIso8601String(),
                    'revoked_at' => $key->revoked_at?->toIso8601String(),
                    'is_usable' => $key->isUsable(),
                ])
                ->all(),
            'scopes' => ApiScope::options(),
            'recentRequests' => ApiRequest::query()
                ->with('apiKey:id,name')
                ->latest('created_at')
                ->limit(20)
                ->get()
                ->map(fn (ApiRequest $request): array => [
                    'id' => $request->id,
                    'key' => $request->apiKey?->name,
                    'method' => $request->method,
                    'path' => $request->path,
                    'status' => $request->status,
                    'duration_ms' => $request->duration_ms,
                    'created_at' => $request->created_at->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scopes' => ['required', 'array', 'min:1'],
            // Grantable, not every case: the enum names the whole planned
            // surface, and handing out a scope with no endpoint behind it
            // would promise access that does not exist (§124).
            'scopes.*' => [Rule::in(ApiScope::grantableValues())],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ], [
            'scopes.required' => 'Choose at least one scope for this key.',
            'expires_at.after' => 'An expiry date must be in the future.',
        ]);

        $minted = ApiKey::mint(
            $validated['name'],
            array_values($validated['scopes']),
            $request->user()?->id,
            isset($validated['expires_at']) ? now()->parse($validated['expires_at']) : null,
        );

        // The scopes granted, never the secret: an audit trail must not become
        // a second place the key can be read from (§55).
        app(AuditRecorder::class)->record(
            AuditAction::ApiKeyCreated,
            $minted['key'],
            after: ['name' => $minted['key']->name, 'scopes' => $minted['key']->scopes],
        );

        // Flashed once, for the one render that shows it. §48 requires the
        // secret be unrecoverable afterwards, so it is never stored anywhere
        // it could be read again.
        return back()->with('newApiKey', [
            'name' => $minted['key']->name,
            'token' => $minted['token'],
        ]);
    }

    public function destroy(ApiKey $apiKey): RedirectResponse
    {
        // Revoked, not deleted: the request log references it, and "who was
        // calling us last Tuesday" must stay answerable.
        $apiKey->forceFill(['revoked_at' => now()])->save();

        app(AuditRecorder::class)->record(
            AuditAction::ApiKeyRevoked,
            $apiKey,
            after: ['name' => $apiKey->name],
        );

        return back()->with('success', "“{$apiKey->name}” has been revoked.");
    }
}
