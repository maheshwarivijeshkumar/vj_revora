<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Domain\Branding\Brand;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * PWA manifest, generated from the active brand rather than a static file, so
 * the installed-app icon and theme colour follow the chosen identity.
 */
final class ManifestController extends Controller
{
    public function __invoke(Brand $brand): JsonResponse
    {
        $current = $brand->current();
        $assets = $current['assets'];

        return response()->json([
            'name' => $current['name'],
            'short_name' => $current['name'],
            'description' => $current['tagline'],
            'start_url' => '/dashboard',
            'display' => 'standalone',
            'background_color' => '#f6f8fc',
            'theme_color' => $current['primary'],
            'icons' => [
                ['src' => $assets['app_icon_192'], 'sizes' => '192x192', 'type' => 'image/png'],
                ['src' => $assets['app_icon_512'], 'sizes' => '512x512', 'type' => 'image/png'],
                ['src' => $assets['app_icon_512'], 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ])->header('Cache-Control', 'public, max-age=3600');
    }
}
