<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Audit\Listeners\RecordAccessAudit;
use App\Domain\Audit\Listeners\RecordDomainAudit;
use App\Domain\Deals\Events\DealStageChanged;
use App\Domain\Identity\TenantUserProvider;
use App\Domain\Leads\Events\LeadCaptured;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Webhooks\Listeners\SendDealWebhooks;
use App\Domain\Webhooks\Listeners\SendLeadWebhooks;
use App\Models\ApiKey;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request/job. Everything tenant-scoped reads
        // from this instance, so there is exactly one answer to "whose data
        // is this?" at any moment.
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
        Model::unguard(false);

        Date::use(CarbonImmutable::class);

        // Credential lookup must cross the tenant boundary; see the class
        // docblock for why this is the one sanctioned exception.
        Auth::provider(
            'tenant-users',
            fn ($app, array $config): TenantUserProvider => new TenantUserProvider(
                $app['hash'],
                $config['model'],
            ),
        );

        $this->configureRateLimiting();
        $this->registerDomainListeners();
    }

    /**
     * Listeners that live in their domain rather than in app/Listeners.
     *
     * Laravel only auto-discovers the latter, and a webhook listener belongs
     * next to the webhook code it is part of rather than in a flat folder that
     * says nothing about what it does.
     */
    private function registerDomainListeners(): void
    {
        Event::listen(LeadCaptured::class, SendLeadWebhooks::class);
        Event::listen(DealStageChanged::class, SendDealWebhooks::class);

        Event::listen(Login::class, [RecordAccessAudit::class, 'handleLogin']);
        Event::listen(Logout::class, [RecordAccessAudit::class, 'handleLogout']);
        Event::listen(Failed::class, [RecordAccessAudit::class, 'handleFailed']);

        Event::listen(LeadCaptured::class, [RecordDomainAudit::class, 'handleLeadCaptured']);
        Event::listen(DealStageChanged::class, [RecordDomainAudit::class, 'handleDealStageChanged']);
    }

    /**
     * API rate limits (§51).
     *
     * Keyed on the API key rather than the IP: one workspace behind a shared
     * NAT must not be able to throttle another, and a single integration
     * spread across several servers is still one consumer.
     *
     * Unauthenticated requests fall back to the IP, which is all we have and
     * is the case worth limiting hardest.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request): Limit {
            $key = $request->attributes->get('api_key');

            if (! $key instanceof ApiKey) {
                return Limit::perMinute(30)->by($request->ip() ?? 'unknown');
            }

            return Limit::perMinute($key->rate_limit ?? 300)
                ->by('api-key:'.$key->id)
                ->response(fn (Request $r, array $headers) => response()->json([
                    'message' => 'Too many requests. Slow down and try again shortly.',
                ], 429, $headers));
        });
    }
}
