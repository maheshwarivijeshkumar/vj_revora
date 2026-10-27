<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Opportunities\Sources\UpworkClient;
use App\Http\Controllers\Controller;
use App\Models\UpworkAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Connecting Upwork, read-only, to find job postings.
 */
final class UpworkController extends Controller
{
    private const STATE_KEY = 'upwork_oauth_state';

    public function connect(Request $request, UpworkClient $upwork): RedirectResponse
    {
        if (! $upwork->isConfigured()) {
            return back()->with('error', 'Upwork is not set up on this server yet. Add the approved API key (UPWORK_CLIENT_ID and UPWORK_CLIENT_SECRET) first.');
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);

        return redirect()->away($upwork->authorizationUrl($state, route('settings.upwork.callback')));
    }

    public function callback(Request $request, UpworkClient $upwork): RedirectResponse
    {
        $expected = $request->session()->pull(self::STATE_KEY);

        if (! is_string($expected) || ! hash_equals($expected, $request->string('state')->toString())) {
            return to_route('settings.setup')->with('error', 'That Upwork sign-in link expired or did not come from here. Connect again.');
        }

        if (! $request->filled('code')) {
            return to_route('settings.setup')->with('error', 'Upwork was not connected: permission was not granted.');
        }

        try {
            $credentials = $upwork->exchangeCode($request->string('code')->toString(), route('settings.upwork.callback'));
        } catch (ProviderAuthException $e) {
            return to_route('settings.setup')->with('error', $e->getMessage());
        }

        (UpworkAccount::query()->first() ?? new UpworkAccount)->forceFill([
            'name' => 'Upwork',
            'credentials' => $credentials,
            'status' => ConnectionStatus::Connected,
            'last_error' => null,
        ])->save();

        return to_route('settings.setup')->with('success', 'Upwork is connected. Matching jobs appear under Opportunities within two hours.');
    }

    public function destroy(): RedirectResponse
    {
        UpworkAccount::query()->first()?->delete();

        return back()->with('success', 'Upwork is disconnected. Jobs already found stay under Opportunities.');
    }
}
