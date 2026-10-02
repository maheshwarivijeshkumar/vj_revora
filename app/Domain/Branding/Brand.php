<?php

declare(strict_types=1);

namespace App\Domain\Branding;

use Illuminate\Support\Facades\Session;

/**
 * Resolves the active brand identity.
 *
 * One place answers "which brand are we?", so the blade shell, Inertia props,
 * favicon tags and transactional email cannot disagree.
 *
 * Nothing branches on a brand key — callers ask for the resolved values.
 */
final class Brand
{
    private const SESSION_KEY = 'brand.preview';

    /**
     * The active brand key: a session preview if one is set and previewing is
     * enabled, otherwise the configured default.
     */
    public function key(): string
    {
        $default = (string) config('brand.active', 'revora');

        if (! $this->previewEnabled()) {
            return $this->exists($default) ? $default : 'revora';
        }

        $preview = Session::get(self::SESSION_KEY);

        if (is_string($preview) && $this->exists($preview)) {
            return $preview;
        }

        return $this->exists($default) ? $default : 'revora';
    }

    /**
     * @return array{key: string, name: string, tagline: string, concept: string, primary: string, secondary: string, accent: string, assets: array<string, string>}
     */
    public function current(): array
    {
        return $this->get($this->key());
    }

    /**
     * @return array{key: string, name: string, tagline: string, concept: string, primary: string, secondary: string, accent: string, assets: array<string, string>}
     */
    public function get(string $key): array
    {
        /** @var array<string, array<string, string>> $brands */
        $brands = config('brand.brands', []);
        $brand = $brands[$key] ?? [];

        return [
            'key' => $key,
            'name' => $brand['name'] ?? 'Revora',
            'tagline' => $brand['tagline'] ?? '',
            'concept' => $brand['concept'] ?? '',
            'primary' => $brand['primary'] ?? '#059669',
            'secondary' => $brand['secondary'] ?? '#10B981',
            'accent' => $brand['accent'] ?? '#2563EB',
            'assets' => $this->assets($key),
        ];
    }

    /**
     * Every candidate, for the brand picker.
     *
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        /** @var array<string, array<string, string>> $brands */
        $brands = config('brand.brands', []);

        // array_map over array_keys already yields a list.
        return array_map(
            fn (string $key): array => $this->get($key),
            array_keys($brands),
        );
    }

    /**
     * Paths under public/brand/<key>/, produced by scripts/brand/generate.mjs.
     *
     * @return array<string, string>
     */
    public function assets(string $key): array
    {
        $base = "/brand/{$key}";

        return [
            'favicon_svg' => "{$base}/favicon.svg",
            'favicon_ico' => "{$base}/favicon.ico",
            'favicon_dark_ico' => "{$base}/favicon-dark.ico",
            'app_icon_180' => "{$base}/app-icon-180.png",
            'app_icon_192' => "{$base}/app-icon-192.png",
            'app_icon_512' => "{$base}/app-icon-512.png",
            'app_icon_webp' => "{$base}/app-icon-512.webp",
        ];
    }

    public function exists(string $key): bool
    {
        return array_key_exists($key, (array) config('brand.brands', []));
    }

    public function previewEnabled(): bool
    {
        return (bool) config('brand.allow_preview', false);
    }

    /**
     * Sets a session-scoped preview. Ignored when previewing is disabled, so
     * a stale session cannot re-theme production.
     */
    public function preview(string $key): void
    {
        if ($this->previewEnabled() && $this->exists($key)) {
            Session::put(self::SESSION_KEY, $key);
        }
    }

    public function clearPreview(): void
    {
        Session::forget(self::SESSION_KEY);
    }
}
