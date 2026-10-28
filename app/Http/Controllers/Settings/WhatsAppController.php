<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * The workspace's WhatsApp Business number (§15).
 *
 * The phone number ID and a permanent access token come from the workspace's
 * own Meta Business app — Meta's supported workflow for the Cloud API — so
 * this is the one place a token is entered by hand (§11). It is encrypted and
 * never shown again.
 */
final class WhatsAppController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('integrations.index');
    }

    public function update(Request $request): RedirectResponse
    {
        $account = WhatsAppAccount::query()->first();

        $validated = $request->validate([
            'phone_number_id' => ['required', 'string', 'max:64', 'regex:/^\d+$/'],
            'business_account_id' => ['nullable', 'string', 'max:64', 'regex:/^\d+$/'],
            'display_phone_number' => ['nullable', 'string', 'max:32'],
            // Required the first time; leaving it blank later keeps the old one.
            'access_token' => [$account === null ? 'required' : 'nullable', 'string', 'max:1000'],
            'welcome_template' => ['nullable', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'template_language' => ['required', 'string', 'max:10'],
            'auto_send_welcome' => ['required', 'boolean'],
        ], [
            'phone_number_id.regex' => 'The phone number ID is the number Meta shows under WhatsApp → API Setup, digits only.',
            'welcome_template.regex' => 'Template names use lower-case letters, numbers and underscores, exactly as approved in Meta.',
        ]);

        $taken = WhatsAppAccount::query()->withoutGlobalScopes()
            ->where('phone_number_id', $validated['phone_number_id'])
            ->when($account !== null, fn ($q) => $q->whereKeyNot($account->id))
            ->exists();

        if ($taken) {
            return back()->withErrors(['phone_number_id' => 'This WhatsApp number is already connected to another workspace.']);
        }

        $attributes = Arr::except($validated, ['access_token']);

        if (filled($validated['access_token'] ?? null)) {
            $attributes['access_token'] = $validated['access_token'];
        }

        ($account ?? new WhatsAppAccount)->forceFill([
            ...$attributes,
            'status' => ConnectionStatus::Connected,
            'last_error' => null,
        ])->save();

        return back()->with('success', 'WhatsApp settings saved.');
    }

    public function destroy(): RedirectResponse
    {
        WhatsAppAccount::query()->first()?->delete();

        return back()->with('success', 'WhatsApp is disconnected. Messages already sent stay on each lead.');
    }
}
