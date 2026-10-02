<?php

declare(strict_types=1);

use App\Domain\Branding\Brand;
use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureApiScope;
use App\Http\Middleware\EnsureFeature;
use App\Http\Middleware\EnsureTenantIsActive;
use App\Http\Middleware\EnsureWithinLimit;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RecordApiRequest;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ResolveTenant runs before Inertia so shared props can read the
        // tenant, and before anything queries a tenant-owned model.
        $middleware->web(append: [
            ResolveTenant::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // The API authenticates by key, which is also what identifies the
        // workspace, so ResolveTenant has no part to play here.
        $middleware->api(remove: [
            ResolveTenant::class,
        ]);

        /*
         * ResolveTenant must run before route model binding.
         *
         * Binding resolves {deal} through the tenant-scoped model, so with no
         * tenant bound yet the global scope fails closed and every bound route
         * 404s. Appending to the group is not enough: SubstituteBindings is
         * ordered by the priority list, not by group position.
         *
         * It cannot simply be prepended either, because it needs the session
         * to know who is signed in. Immediately before SubstituteBindings is
         * the one correct position.
         */
        $middleware->prependToPriorityList(
            SubstituteBindings::class,
            ResolveTenant::class,
        );

        $middleware->alias([
            'tenant.active' => EnsureTenantIsActive::class,
            'feature' => EnsureFeature::class,
            'limit' => EnsureWithinLimit::class,
            'api.key' => AuthenticateApiKey::class,
            'api.log' => RecordApiRequest::class,
            'scope' => EnsureApiScope::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
        |----------------------------------------------------------------------
        | Branded error pages
        |----------------------------------------------------------------------
        |
        | Renders the Inertia Error page for the statuses a visitor can
        | actually hit, so a failure looks like part of the product rather than
        | a server default (§59, §69).
        |
        | Debug mode is left alone: the exception page is far more useful than
        | branded copy while developing. API and JSON requests keep their
        | normal JSON error shape.
        |
        */
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            $renderable = [401, 402, 403, 404, 419, 429, 500, 503];

            if (
                app()->hasDebugModeEnabled()
                || $request->is('api/*')
                || $request->expectsJson()
                || ! in_array($status, $renderable, true)
            ) {
                return $response;
            }

            $retryAfter = $response->headers->get('Retry-After');

            return inertia('Error', [
                'status' => $status,
                'message' => $e instanceof HttpExceptionInterface ? $e->getMessage() : null,
                // Correlates the visitor's screen with the logged exception
                // without exposing anything about it.
                'reference' => $status >= 500 ? strtoupper(substr(md5((string) $e->getFile().$e->getLine().now()->timestamp), 0, 12)) : null,
                'retryAfter' => is_numeric($retryAfter) ? (int) $retryAfter : null,
                'brand' => app(Brand::class)->current(),
                'authenticated' => $request->user() !== null,
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
