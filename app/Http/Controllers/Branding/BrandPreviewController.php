<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branding;

use App\Domain\Branding\Brand;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Live preview of the eight candidate identities (§105).
 *
 * Exists so the final brand can be chosen against the real interface rather
 * than a contact sheet. The choice is session-scoped: it re-themes the
 * previewer's own session only, never other visitors, and never the deployed
 * default until BRAND_KEY is set.
 */
final class BrandPreviewController extends Controller
{
    public function __construct(
        private readonly Brand $brand,
    ) {}

    public function index(): Response
    {
        abort_unless($this->brand->previewEnabled(), HttpResponse::HTTP_NOT_FOUND);

        return Inertia::render('Brand', [
            'brands' => $this->brand->all(),
            'active' => $this->brand->key(),
            'configured' => (string) config('brand.active'),
            // The page uses the public site shell, so it needs what that
            // layout needs: the active identity plus contact and social links.
            'brand' => $this->brand->current(),
            'contact' => array_filter((array) config('site.contact')),
            'social' => array_filter((array) config('site.social')),
            // Where "see it in context" should go. Resolved here rather than
            // guessed client-side, because SITE_MODE decides whether the
            // marketing home page lives at / or /home.
            'homeUrl' => config('site.mode') === 'live' ? '/' : '/home',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($this->brand->previewEnabled(), HttpResponse::HTTP_NOT_FOUND);

        $validated = $request->validate([
            'brand' => ['required', 'string'],
        ]);

        $key = $validated['brand'];

        if (! $this->brand->exists($key)) {
            return back()->with('error', 'That brand does not exist.');
        }

        $this->brand->preview($key);

        // No success flash: the client raises this toast itself so it can
        // offer to navigate, which a flash string cannot carry.
        return back();
    }

    public function destroy(): RedirectResponse
    {
        $this->brand->clearPreview();

        return back()->with('info', 'Reverted to the configured brand.');
    }
}
