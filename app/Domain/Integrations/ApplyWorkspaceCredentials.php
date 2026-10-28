<?php

declare(strict_types=1);

namespace App\Domain\Integrations;

use App\Domain\Tenancy\TenantContext;
use App\Models\IntegrationApp;
use Illuminate\Database\QueryException;

/**
 * Copies the workspace's saved app keys over the environment defaults.
 *
 * A blank saved value leaves the environment value in place. Switching
 * workspace puts the previous workspace's keys back before applying the next.
 */
final class ApplyWorkspaceCredentials
{
    /** @var array<string, mixed> */
    private array $replaced = [];

    public function apply(): void
    {
        $this->clear();

        if (! app(TenantContext::class)->check()) {
            return;
        }

        try {
            $apps = IntegrationApp::query()->get();
        } catch (QueryException) {
            return;
        }

        foreach ($apps->sortBy(fn (IntegrationApp $app): int => $app->provider === 'whatsapp' ? 1 : 0) as $app) {
            if ($app->enabled) {
                $this->overlay($app);
            } else {
                $this->suppress($app->provider);
            }
        }

        $whatsapp = $apps->first(fn (IntegrationApp $app): bool => $app->provider === 'whatsapp' && $app->enabled);
        $metaSecret = config('services.meta.client_secret');
        $originalWhatsapp = $this->replaced['services.whatsapp.app_secret'] ?? config('services.whatsapp.app_secret');
        $originalMeta = $this->replaced['services.meta.client_secret'] ?? $metaSecret;

        if ($whatsapp !== null && blank($whatsapp->client_secret) && filled($metaSecret) && (blank($originalWhatsapp) || $originalWhatsapp === $originalMeta)) {
            $this->put('services.whatsapp.app_secret', $metaSecret);
        }
    }

    public function clear(): void
    {
        foreach ($this->replaced as $key => $original) {
            config([$key => $original]);
        }

        $this->replaced = [];
    }

    /**
     * An explicit off switch wins over keys saved in the environment.
     */
    private function suppress(string $provider): void
    {
        match ($provider) {
            'meta', 'linkedin', 'microsoft', 'upwork' => $this->blankPair($provider),
            'google' => $this->blankGoogle(),
            'whatsapp' => $this->blankWhatsapp(),
            default => null,
        };
    }

    private function blankPair(string $service): void
    {
        $this->put("services.{$service}.client_id", null);
        $this->put("services.{$service}.client_secret", null);
    }

    private function blankGoogle(): void
    {
        $this->blankPair('google');
        $this->put('services.google.places_key', null);
    }

    private function blankWhatsapp(): void
    {
        $this->put('services.whatsapp.app_secret', null);
        $this->put('services.whatsapp.verify_token', null);
    }

    private function overlay(IntegrationApp $app): void
    {
        $id = filled($app->client_id) ? $app->client_id : null;
        $secret = filled($app->client_secret) ? $app->client_secret : null;
        $apiKey = filled($app->api_key) ? $app->api_key : null;

        match ($app->provider) {
            'meta' => $this->meta($id, $secret),
            'linkedin' => $this->pair('linkedin', $id, $secret),
            'google' => $this->google($id, $secret, $apiKey),
            'microsoft' => $this->pair('microsoft', $id, $secret),
            'upwork' => $this->pair('upwork', $id, $secret),
            'whatsapp' => $this->whatsapp($secret, $apiKey),
            default => null,
        };
    }

    private function meta(?string $id, ?string $secret): void
    {
        $this->pair('meta', $id, $secret);
    }

    private function google(?string $id, ?string $secret, ?string $placesKey): void
    {
        $this->pair('google', $id, $secret);

        if ($placesKey !== null) {
            $this->put('services.google.places_key', $placesKey);
        }
    }

    private function whatsapp(?string $secret, ?string $verifyToken): void
    {
        if ($secret !== null) {
            $this->put('services.whatsapp.app_secret', $secret);
        }

        if ($verifyToken !== null) {
            $this->put('services.whatsapp.verify_token', $verifyToken);
        }
    }

    private function pair(string $service, ?string $id, ?string $secret): void
    {
        if ($id !== null) {
            $this->put("services.{$service}.client_id", $id);
        }

        if ($secret !== null) {
            $this->put("services.{$service}.client_secret", $secret);
        }
    }

    private function put(string $key, mixed $value): void
    {
        if (! array_key_exists($key, $this->replaced)) {
            $this->replaced[$key] = config($key);
        }

        config([$key => $value]);
    }
}
