<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Domain\Messaging\Enums\MailboxProvider;
use App\Domain\Messaging\Mailboxes;
use App\Http\Controllers\Controller;
use App\Jobs\SyncMailboxJob;
use App\Models\EmailAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Connecting mailboxes the CRM sends from and reads replies out of (§25).
 */
final class EmailAccountController extends Controller
{
    private const STATE_KEY = 'mailbox_oauth_state';

    public function __construct(
        private readonly Mailboxes $mailboxes,
    ) {}

    public function index(): Response
    {
        return Inertia::render('settings/Email', [
            'accounts' => EmailAccount::query()->orderByDesc('is_default')->orderBy('email')->get()
                ->map(fn (EmailAccount $account): array => [
                    'id' => $account->uuid,
                    'email' => $account->email,
                    'from_name' => $account->from_name,
                    'provider' => $account->provider->value,
                    'provider_label' => $account->provider->label(),
                    'status' => $account->status->value,
                    'status_label' => $account->status->label(),
                    'status_tone' => $account->status->tone(),
                    'is_default' => $account->is_default,
                    'last_synced_at' => $account->last_synced_at?->toIso8601String(),
                    'last_error' => $account->last_error,
                ])->all(),
            'oauth' => array_map(fn (MailboxProvider $provider): array => [
                'key' => $provider->value,
                'label' => $provider->label(),
                'configured' => $this->mailboxes->oauth($provider)->isConfigured(),
                'connect_url' => route('settings.email.connect', $provider),
                'callback_url' => route('settings.email.callback', $provider),
            ], [MailboxProvider::Google, MailboxProvider::Microsoft]),
        ]);
    }

    /**
     * Adds an SMTP + IMAP mailbox, after proving the login works: a mailbox
     * that cannot send would only fail later, silently, in the sequence.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:500'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
            'imap_host' => ['required', 'string', 'max:255'],
            'imap_port' => ['required', 'integer', 'between:1,65535'],
            'imap_encryption' => ['required', Rule::in(['ssl', 'tls', 'none'])],
        ]);

        $account = new EmailAccount([
            'provider' => MailboxProvider::Smtp,
            'email' => mb_strtolower($validated['email']),
            'from_name' => $validated['from_name'],
            'status' => ConnectionStatus::Connected,
            'credentials' => Arr::except($validated, ['email', 'from_name']),
        ]);

        try {
            // Reading the inbox proves the IMAP login; it changes nothing.
            foreach ($this->mailboxes->for(MailboxProvider::Smtp)->fetchInbox($account, now()->subMinute()) as $ignored) {
                break;
            }
        } catch (ProviderAuthException|ProviderRequestException $e) {
            return back()->withErrors(['password' => $e->getMessage()])->withInput();
        }

        $this->save($account, $request->user()?->id);

        return to_route('settings.email')->with('success', "{$account->email} is connected. Follow-ups will now be sent from it.");
    }

    public function connect(Request $request, MailboxProvider $provider): RedirectResponse
    {
        $mailbox = $this->mailboxes->oauth($provider);

        if (! $mailbox->isConfigured()) {
            return back()->with('error', "{$provider->label()} sign-in is not set up on this server yet. Add its client ID and secret to the environment.");
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, ['provider' => $provider->value, 'state' => $state]);

        return redirect()->away($mailbox->authorizationUrl($state, route('settings.email.callback', $provider)));
    }

    public function callback(Request $request, MailboxProvider $provider): RedirectResponse
    {
        $expected = $request->session()->pull(self::STATE_KEY);

        if (! is_array($expected)
            || $expected['provider'] !== $provider->value
            || ! hash_equals((string) $expected['state'], $request->string('state')->toString())) {
            return to_route('settings.email')->with('error', 'That sign-in link expired or did not come from here. Connect the mailbox again.');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return to_route('settings.email')->with('error', 'The mailbox was not connected: permission was not granted.');
        }

        try {
            $connected = $this->mailboxes->oauth($provider)->connect(
                $request->string('code')->toString(),
                route('settings.email.callback', $provider),
            );
        } catch (ProviderAuthException|ProviderRequestException $e) {
            return to_route('settings.email')->with('error', $e->getMessage());
        }

        $account = EmailAccount::query()->firstOrNew(['email' => $connected->email]);
        $account->fill([
            'provider' => $provider,
            'from_name' => $account->from_name ?? $connected->name,
            'status' => ConnectionStatus::Connected,
            'credentials' => $connected->credentials,
            'last_error' => null,
        ]);

        $this->save($account, $request->user()?->id);

        return to_route('settings.email')->with('success', "{$account->email} is connected.");
    }

    public function update(Request $request, EmailAccount $account): RedirectResponse
    {
        $validated = $request->validate([
            'from_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('is_default')) {
            EmailAccount::query()->whereKeyNot($account->id)->update(['is_default' => false]);
        }

        $account->fill($validated)->save();

        return back()->with('success', 'Mailbox updated.');
    }

    public function sync(EmailAccount $account): RedirectResponse
    {
        SyncMailboxJob::dispatch($account->id);

        return back()->with('success', "Checking {$account->email} for replies.");
    }

    public function destroy(EmailAccount $account): RedirectResponse
    {
        $account->delete();

        // Keep a default, so follow-ups keep a sender if another mailbox exists.
        if ($account->is_default) {
            EmailAccount::query()->orderBy('id')->first()?->forceFill(['is_default' => true])->save();
        }

        return back()->with('success', "{$account->email} is disconnected. Emails already sent stay on each lead.");
    }

    private function save(EmailAccount $account, ?int $userId): void
    {
        $account->connected_by ??= $userId;
        $account->is_default = $account->is_default || ! EmailAccount::query()->where('is_default', true)->exists();
        $account->save();
    }
}
