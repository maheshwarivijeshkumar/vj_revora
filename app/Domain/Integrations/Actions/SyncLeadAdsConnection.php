<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\DataObjects\ProviderLead;
use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Domain\Integrations\LeadAdsProviders;
use App\Domain\Leads\Actions\CaptureLead;
use App\Domain\Leads\Exceptions\UnusableLeadException;
use App\Domain\Leads\Services\LeadNormalizer;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncRun;
use App\Models\LeadEvent;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Pulls new lead-ad submissions for one connection into the CRM (§57).
 *
 * Polling, run hourly, with each provider lead id recorded as the capture's
 * provider_event_id. That id is uniquely indexed per tenant (§58), so the
 * window can overlap the previous run on purpose: a lead seen twice is skipped,
 * and a lead that arrived while the last run was in flight is not missed.
 */
final readonly class SyncLeadAdsConnection
{
    /** How far back the first sync looks. Meta keeps leads for 90 days. */
    public const INITIAL_LOOKBACK_DAYS = 90;

    /** Overlap with the previous run, for leads the provider indexed late. */
    private const OVERLAP_MINUTES = 60;

    public function __construct(
        private LeadAdsProviders $providers,
        private LeadNormalizer $normalizer,
        private CaptureLead $capture,
    ) {}

    public function handle(IntegrationConnection $connection, string $trigger): IntegrationSyncRun
    {
        $startedAt = now();

        $run = IntegrationSyncRun::create([
            'integration_connection_id' => $connection->id,
            'trigger' => $trigger,
            'status' => IntegrationSyncRun::STATUS_RUNNING,
            'leads_fetched' => 0,
            'leads_created' => 0,
            'leads_merged' => 0,
            'leads_skipped' => 0,
            'started_at' => $startedAt,
        ]);

        $since = $connection->last_synced_at?->copy()->subMinutes(self::OVERLAP_MINUTES)
            ?? $startedAt->copy()->subDays(self::INITIAL_LOOKBACK_DAYS);

        try {
            foreach ($this->providers->for($connection->provider)->fetchLeads($connection, $since) as $lead) {
                $run->leads_fetched++;
                $this->captureOne($connection, $lead, $run);
            }
        } catch (ProviderAuthException $e) {
            return $this->fail($connection, $run, ConnectionStatus::NeedsReconnect, $e->getMessage());
        } catch (ProviderRequestException $e) {
            return $this->fail($connection, $run, ConnectionStatus::Failing, $e->getMessage());
        }

        // Only advanced on success: a failed run is covered again next time,
        // and the event-id check keeps the leads it did capture from doubling.
        $connection->forceFill([
            'status' => ConnectionStatus::Connected,
            'last_synced_at' => $startedAt,
            'last_error' => null,
        ])->save();

        $run->forceFill([
            'status' => IntegrationSyncRun::STATUS_SUCCEEDED,
            'finished_at' => now(),
        ])->save();

        return $run;
    }

    private function captureOne(IntegrationConnection $connection, ProviderLead $lead, IntegrationSyncRun $run): void
    {
        $provider = $connection->provider->value;

        $alreadyCaptured = LeadEvent::query()
            ->where('provider', $provider)
            ->where('provider_event_id', $lead->id)
            ->exists();

        if ($alreadyCaptured) {
            $run->leads_skipped++;

            return;
        }

        try {
            $result = $this->capture->handle(
                $this->normalizer->normalize($lead->toPayload()),
                $connection->source,
                providerEvent: [
                    'lead_id' => $lead->id,
                    'submitted_at' => $lead->submittedAt->toIso8601String(),
                    'account' => $connection->account_name,
                    'fields' => $lead->fields,
                ],
                providerEventId: $lead->id,
            );
        } catch (UnusableLeadException|UniqueConstraintViolationException) {
            // No way to reach the person, or a concurrent run got there first.
            $run->leads_skipped++;

            return;
        }

        $result->isNew() ? $run->leads_created++ : $run->leads_merged++;
    }

    private function fail(
        IntegrationConnection $connection,
        IntegrationSyncRun $run,
        ConnectionStatus $status,
        string $message,
    ): IntegrationSyncRun {
        $connection->forceFill(['status' => $status, 'last_error' => $message])->save();

        $run->forceFill([
            'status' => IntegrationSyncRun::STATUS_FAILED,
            'error' => $message,
            'finished_at' => now(),
        ])->save();

        return $run;
    }
}
