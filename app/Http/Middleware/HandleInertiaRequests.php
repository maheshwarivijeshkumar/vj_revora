<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Billing\Entitlements;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props shared with every Inertia response.
     *
     * Permissions and entitlements are shared so the UI can hide and disable
     * consistently with what the server would actually allow — one source of
     * truth rather than two. They are a UX affordance, never the security
     * boundary: policies and entitlement middleware remain authoritative.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $tenant = app(TenantContext::class)->get();

        return [
            ...parent::share($request),

            'name' => config('app.name'),

            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar_path,
                    'timezone' => $user->timezone,
                    'locale' => $user->locale,
                ],
            ],

            'tenant' => $tenant === null ? null : [
                'id' => $tenant->id,
                'uuid' => $tenant->uuid,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status->value,
            ],

            // Resolved lazily: an unauthenticated request should not pay for a
            // permission lookup that will be empty anyway.
            'permissions' => fn (): array => $user === null
                ? []
                : $user->getAllPermissions()->pluck('name')->all(),

            'entitlements' => fn (): array => $tenant === null
                ? []
                : app(Entitlements::class)->snapshot($tenant),

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
                // The one and only delivery of a freshly minted API secret.
                // It lives in the session for a single request and is never
                // persisted in a readable form (§48).
                'newApiKey' => fn () => $request->session()->get('newApiKey'),
                // A queued bulk action the page should start polling (§113).
                'bulkOperation' => fn () => $request->session()->get('bulkOperation'),
            ],
        ];
    }
}
