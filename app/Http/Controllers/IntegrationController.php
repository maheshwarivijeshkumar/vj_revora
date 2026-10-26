<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Integrations\Actions\SyncLeadAdsConnection;
use App\Domain\Integrations\DataObjects\DiscoveredAccount;
use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Integrations\Enums\LeadAdsPlatform;
use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Domain\Integrations\LeadAdsProviders;
use App\Domain\Leads\Enums\LeadSourceType;
use App\Jobs\SyncLeadAdsConnectionJob;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncRun;
use App\Models\LeadSource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        ]);
    }

    public function connect(Request $request, LeadAdsPlatform $platform): RedirectResponse
    {
        $provider = $this->providers->for($platform);

        if (! $provider->isConfigured()) {
            return back()->with('error', "{$platform->label()} is not set up on this server yet. Add the app credentials to the environment first.");
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
