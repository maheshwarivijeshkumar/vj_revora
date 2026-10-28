<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Integrations\Actions\SyncLeadAdsConnection;
use App\Domain\Integrations\ApplyWorkspaceCredentials;
use App\Domain\Integrations\DataObjects\DiscoveredAccount;
use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Integrations\Enums\LeadAdsPlatform;
use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Domain\Integrations\LeadAdsProviders;
use App\Domain\Leads\Enums\LeadSourceType;
use App\Jobs\SyncLeadAdsConnectionJob;
use App\Models\IntegrationApp;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncRun;
use App\Models\LeadSource;
use App\Models\WhatsAppAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Connecting lead-ad accounts and watching them sync (§11, §57, §65).
 *
 * Connection is OAuth only: the user signs in at the provider and picks what
 * to share, and no token is ever typed or shown (§11).
 */
final class IntegrationController extends Controller
{
    private const STATE_KEY = 'lead_ads_oauth_state';

    public function __construct(
        private readonly LeadAdsProviders $providers,
    ) {}

    public function index(): Response
    {
        $connections = IntegrationConnection::query()
            ->with(['syncRuns' => fn ($q) => $q->latest('started_at')->limit(1)])
            ->orderBy('provider')
            ->orderBy('account_name')
            ->get();

        return Inertia::render('settings/Integrations', [
            'platforms' => array_map(fn (LeadAdsPlatform $platform): array => [
                'key' => $platform->value,
                'label' => $platform->label(),
                'account_noun' => $platform->accountNoun(),
                'configured' => $this->providers->for($platform)->isConfigured(),
                'connect_url' => route('integrations.connect', $platform),
                'callback_url' => $this->callbackUrl($platform),
            ], LeadAdsPlatform::cases()),
            'connections' => $connections->map(fn (IntegrationConnection $c): array => [
                'id' => $c->uuid,
                'platform' => $c->provider->value,
                'platform_label' => $c->provider->label(),
                'name' => $c->account_name,
                'status' => $c->status->value,
                'status_label' => $c->status->label(),
                'status_tone' => $c->status->tone(),
                'last_synced_at' => $c->last_synced_at?->toIso8601String(),
                'token_expires_at' => $c->token_expires_at?->toIso8601String(),
                'last_error' => $c->last_error,
                'source_id' => $c->lead_source_id,
                'last_run' => $this->run($c->syncRuns->first()),
            ])->all(),
            'recentRuns' => IntegrationSyncRun::query()
                ->with('connection:id,account_name,provider')
                ->latest('started_at')
                ->limit(15)
                ->get()
                ->map(fn (IntegrationSyncRun $run): array => [
                    ...$this->run($run),
                    'account' => $run->connection?->account_name,
                ])
                ->all(),
            'lookbackDays' => SyncLeadAdsConnection::INITIAL_LOOKBACK_DAYS,
            'credentials' => $this->credentials(),
            'whatsapp' => $this->whatsapp(),
        ]);
    }

    public function updateApp(Request $request, string $provider): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:2000'],
            'api_key' => ['nullable', 'string', 'max:2000'],
        ]);

        $app = IntegrationApp::query()->firstOrNew(['provider' => $provider]);
        $enabling = $request->boolean('enabled');

        if ($enabling) {
            $missing = $this->missingRequiredKeys($request, $app, $provider);

            if ($missing !== []) {
                throw ValidationException::withMessages($missing);
            }
        }

        $app->enabled = $enabling;

        if (array_key_exists('client_id', $validated)) {
            $app->client_id = filled($validated['client_id']) ? $validated['client_id'] : null;
        }

        if (filled($validated['client_secret'] ?? null)) {
            $app->client_secret = $validated['client_secret'];
        }

        if (filled($validated['api_key'] ?? null)) {
            $app->api_key = $validated['api_key'];
        }

        $app->save();

        app(ApplyWorkspaceCredentials::class)->apply();

        return back()->with('success', $enabling ? 'Integration enabled.' : 'Integration disabled.');
    }

    public function connect(Request $request, LeadAdsPlatform $platform): RedirectResponse
    {
        $provider = $this->providers->for($platform);

        if (! $provider->isConfigured()) {
            return back()->with('error', "Add the {$platform->label()} app ID and secret on this page first.");
        }

        // Checked on the way back, so a callback cannot be forged into
        // attaching someone else's account to this workspace.
        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, ['platform' => $platform->value, 'state' => $state]);

        return redirect()->away($provider->authorizationUrl($state, $this->callbackUrl($platform)));
    }

    public function callback(Request $request, LeadAdsPlatform $platform, AuditRecorder $audit): RedirectResponse
    {
        $expected = $request->session()->pull(self::STATE_KEY);

        if (! is_array($expected)
            || $expected['platform'] !== $platform->value
            || ! hash_equals((string) $expected['state'], $request->string('state')->toString())) {
            return to_route('integrations.index')->with('error', 'That sign-in link expired or did not come from here. Start the connection again.');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return to_route('integrations.index')->with('error', "{$platform->label()} was not connected: permission was not granted.");
        }

        try {
            $accounts = $this->providers->for($platform)->connect(
                $request->string('code')->toString(),
                $this->callbackUrl($platform),
            );
        } catch (ProviderAuthException|ProviderRequestException $e) {
            return to_route('integrations.index')->with('error', $e->getMessage());
        }

        if ($accounts === []) {
            return to_route('integrations.index')->with(
                'error',
                "No {$platform->accountNoun()} was shared. Connect again and select at least one you manage.",
            );
        }

        foreach ($accounts as $account) {
            $connection = $this->store($platform, $account, $request->user()?->id);

            $audit->record(
                AuditAction::IntegrationConnected,
                $connection,
                after: ['provider' => $platform->value, 'account' => $connection->account_name],
            );

            SyncLeadAdsConnectionJob::dispatch($connection->id, IntegrationSyncRun::TRIGGER_MANUAL);
        }

        $count = count($accounts);

        return to_route('integrations.index')->with(
            'success',
            "Connected {$count} ".Str::plural($platform->accountNoun(), $count).'. The first sync is importing the last '
                .SyncLeadAdsConnection::INITIAL_LOOKBACK_DAYS.' days of leads.',
        );
    }

    public function sync(IntegrationConnection $connection): RedirectResponse
    {
        SyncLeadAdsConnectionJob::dispatch($connection->id, IntegrationSyncRun::TRIGGER_MANUAL);

        return back()->with('success', "Syncing “{$connection->account_name}”. New leads will appear on the Leads page shortly.");
    }

    public function destroy(IntegrationConnection $connection, AuditRecorder $audit): RedirectResponse
    {
        // The lead source stays: leads already imported keep saying where they
        // came from (§2).
        $connection->delete();

        $audit->record(
            AuditAction::IntegrationDisconnected,
            $connection,
            before: ['provider' => $connection->provider->value, 'account' => $connection->account_name],
        );

        return back()->with('success', "“{$connection->account_name}” is disconnected. Leads already imported are kept.");
    }

    private function store(LeadAdsPlatform $platform, DiscoveredAccount $account, ?int $userId): IntegrationConnection
    {
        $source = LeadSource::query()->firstOrCreate(
            ['key' => $platform->value.':'.$account->accountId],
            [
                'name' => $account->name.' ('.$platform->label().')',
                'type' => LeadSourceType::Connected,
                'provider' => $platform->value,
            ],
        );

        $connection = IntegrationConnection::query()->firstOrNew([
            'provider' => $platform->value,
            'account_id' => $account->accountId,
        ]);

        $connection->forceFill([
            'account_name' => $account->name,
            'status' => ConnectionStatus::Connected,
            'access_token' => $account->accessToken,
            'token_expires_at' => $account->tokenExpiresAt,
            'settings' => $account->settings === [] ? null : $account->settings,
            'lead_source_id' => $source->id,
            'connected_by' => $userId,
            'last_error' => null,
        ])->save();

        return $connection;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function credentials(): array
    {
        $saved = IntegrationApp::query()->get()->keyBy('provider');

        return array_map(function (array $definition) use ($saved): array {
            /** @var IntegrationApp|null $row */
            $row = $saved->get($definition['provider']);

            return [
                ...$definition,
                'enabled' => (bool) $row?->enabled,
                'client_id' => $row->client_id ?? '',
                'secret_set' => filled($row?->client_secret),
                'api_key_set' => filled($row?->api_key),
            ];
        }, $this->credentialDefinitions());
    }

    /**
     * @return array<string, string>
     */
    private function missingRequiredKeys(Request $request, IntegrationApp $app, string $provider): array
    {
        $definition = collect($this->credentialDefinitions())->firstWhere('provider', $provider);

        if (! is_array($definition)) {
            return [];
        }

        $missing = [];

        if ($definition['client_id_required'] && blank($request->input('client_id'))) {
            $missing['client_id'] = 'Enter the '.strtolower((string) $definition['client_id_label']).' to enable this integration.';
        }

        if ($definition['secret_required'] && blank($request->input('client_secret')) && blank($app->client_secret)) {
            $missing['client_secret'] = 'Enter the '.strtolower((string) $definition['secret_label']).' to enable this integration.';
        }

        if ($definition['api_key_required'] && blank($request->input('api_key')) && blank($app->api_key)) {
            $missing['api_key'] = 'Enter the '.strtolower((string) $definition['api_key_label']).' to enable this integration.';
        }

        return $missing;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function credentialDefinitions(): array
    {
        return [
            ['provider' => 'meta', 'label' => 'Facebook & Instagram', 'client_id_label' => 'App ID', 'secret_label' => 'App secret', 'api_key_label' => null, 'client_id_required' => true, 'secret_required' => true, 'api_key_required' => false, 'callback_url' => $this->callbackUrl(LeadAdsPlatform::Meta), 'hint' => 'From the Meta app dashboard. Register the redirect URL under Facebook Login.'],
            ['provider' => 'linkedin', 'label' => 'LinkedIn', 'client_id_label' => 'Client ID', 'secret_label' => 'Client secret', 'api_key_label' => null, 'client_id_required' => true, 'secret_required' => true, 'api_key_required' => false, 'callback_url' => $this->callbackUrl(LeadAdsPlatform::LinkedIn), 'hint' => 'From the LinkedIn app. Register the redirect URL under Auth.'],
            ['provider' => 'whatsapp', 'label' => 'WhatsApp Business', 'client_id_label' => null, 'secret_label' => 'App secret', 'api_key_label' => 'Verify token', 'client_id_required' => false, 'secret_required' => true, 'api_key_required' => true, 'callback_url' => route('webhooks.whatsapp'), 'hint' => 'The app secret signs incoming replies. Pick a verify token and enter the same value in the Meta app.'],
            ['provider' => 'google', 'label' => 'Google', 'client_id_label' => 'Client ID', 'secret_label' => 'Client secret', 'api_key_label' => 'Places API key', 'client_id_required' => true, 'secret_required' => true, 'api_key_required' => false, 'callback_url' => route('settings.email.callback', 'google'), 'hint' => 'The client signs in Gmail. The Places key is only for finding businesses.'],
            ['provider' => 'microsoft', 'label' => 'Microsoft', 'client_id_label' => 'Client ID', 'secret_label' => 'Client secret', 'api_key_label' => null, 'client_id_required' => true, 'secret_required' => true, 'api_key_required' => false, 'callback_url' => route('settings.email.callback', 'microsoft'), 'hint' => 'From the Azure app registration used for Outlook.'],
            ['provider' => 'upwork', 'label' => 'Upwork', 'client_id_label' => 'Client ID', 'secret_label' => 'Client secret', 'api_key_label' => null, 'client_id_required' => true, 'secret_required' => true, 'api_key_required' => false, 'callback_url' => route('settings.upwork.callback'), 'hint' => 'Needs the Read marketplace Job Postings permission.'],
        ];
    }

    /**
     * @return array{account: array<string, mixed>|null, webhook: array{url: string, verify_token_set: bool, app_secret_set: bool}}
     */
    private function whatsapp(): array
    {
        $account = WhatsAppAccount::query()->first();

        return [
            'account' => $account === null ? null : [
                'phone_number_id' => $account->phone_number_id,
                'business_account_id' => $account->business_account_id,
                'display_phone_number' => $account->display_phone_number,
                'welcome_template' => $account->welcome_template,
                'template_language' => $account->template_language,
                'auto_send_welcome' => $account->auto_send_welcome,
                'status' => $account->status->value,
                'status_label' => $account->status->label(),
                'status_tone' => $account->status->tone(),
                'last_error' => $account->last_error,
            ],
            'webhook' => [
                'url' => route('webhooks.whatsapp'),
                'verify_token_set' => filled(config('services.whatsapp.verify_token')),
                'app_secret_set' => filled(config('services.whatsapp.app_secret')),
            ],
        ];
    }

    private function callbackUrl(LeadAdsPlatform $platform): string
    {
        return route('integrations.callback', $platform);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function run(?IntegrationSyncRun $run): ?array
    {
        if ($run === null) {
            return null;
        }

        return [
            'id' => $run->id,
            'trigger' => $run->trigger,
            'status' => $run->status,
            'fetched' => $run->leads_fetched,
            'created' => $run->leads_created,
            'merged' => $run->leads_merged,
            'skipped' => $run->leads_skipped,
            'error' => $run->error,
            'started_at' => $run->started_at->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
        ];
    }
}
