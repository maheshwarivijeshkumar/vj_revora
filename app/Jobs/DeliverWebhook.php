<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domain\Webhooks\Enums\DeliveryStatus;
use App\Domain\Webhooks\Services\WebhookSignature;
use App\Jobs\Concerns\RunsInTenantContext;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Delivers one event to one endpoint, with retries (§49).
 *
 * One job per endpoint rather than one per event: a subscriber that is down must
 * not delay or fail delivery to every other subscriber of the same event.
 */
final class DeliverWebhook implements ShouldQueue
{
    use Queueable, RunsInTenantContext;

    /**
     * Attempts, including the first.
     *
     * Four spread over roughly six minutes covers a deploy or a restart, which
     * is what most failures turn out to be.
     */
    public int $tries = 4;

    /**
     * Longer than the HTTP timeouts below, so a slow host is given up on by the
     * client rather than killed mid-request by the worker and retried against
     * an endpoint that did in fact receive it.
     */
    public int $timeout = 30;

    public function __construct(
        private readonly int $deliveryId,
    ) {
        $this->captureTenant();
        $this->onQueue('webhooks');
    }

    /**
     * Exponential, with jitter.
     *
     * Jitter matters because one subscriber outage produces a burst of
     * deliveries that would otherwise retry in lockstep and arrive as a
     * thundering herd the moment the host comes back up.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [
            10 + random_int(0, 5),
            60 + random_int(0, 15),
            300 + random_int(0, 60),
        ];
    }

    public function handle(): void
    {
        $this->inTenantContext(function (): void {
            $delivery = WebhookDelivery::query()->with('endpoint')->find($this->deliveryId);

            if ($delivery === null) {
                return;
            }

            $endpoint = $delivery->endpoint;

            // Retired while this attempt sat in the queue. Recorded rather than
            // silently dropped, so the log explains why delivery stopped.
            if ($endpoint === null || ! $endpoint->isDeliverable()) {
                $delivery->forceFill([
                    'status' => DeliveryStatus::Failed,
                    'error' => 'The endpoint was disabled before this attempt ran.',
                ])->save();

                return;
            }

            $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $startedAt = microtime(true);

            $delivery->forceFill(['attempt' => $this->attempts()])->save();

            try {
                $response = Http::withHeaders([
                    'User-Agent' => 'Revora-Webhooks/1',
                    WebhookSignature::HEADER => WebhookSignature::header($body, $endpoint->secret),
                    // So a subscriber can deduplicate without parsing the body,
                    // which matters because a retry sends the same event twice.
                    'X-Revora-Delivery' => $delivery->uuid,
                    'X-Revora-Event' => $delivery->event->value,
                ])
                    ->withBody($body, 'application/json')
                    ->connectTimeout(5)
                    ->timeout(15)
                    ->post($endpoint->url);
            } catch (Throwable $e) {
                $this->recordAttempt(
                    $delivery,
                    $e->getMessage(),
                    (int) ((microtime(true) - $startedAt) * 1000),
                );

                // Rethrown so the queue applies the backoff rather than treating
                // a connection failure as a finished job.
                throw $e;
            }

            $durationMs = (int) ((microtime(true) - $startedAt) * 1000);

            if ($response->successful()) {
                $delivery->forceFill([
                    'status' => DeliveryStatus::Delivered,
                    'response_status' => $response->status(),
                    'response_body' => Str::limit($response->body(), WebhookDelivery::RESPONSE_EXCERPT, ''),
                    'duration_ms' => $durationMs,
                    'delivered_at' => now(),
                    'error' => null,
                ])->save();

                $endpoint->recordSuccess();

                return;
            }

            $this->recordAttempt(
                $delivery,
                "The endpoint returned {$response->status()}.",
                $durationMs,
                $response->status(),
                $response->body(),
            );

            // A 4xx other than 408 or 429 will not fix itself, so it is settled
            // here instead of burning three more attempts on a URL that is
            // wrong or a secret the subscriber has not configured.
            if ($this->isPermanent($response->status())) {
                $delivery->forceFill(['status' => DeliveryStatus::Failed])->save();
                $endpoint->recordFailure();

                return;
            }

            throw new RuntimeException(
                "Webhook delivery {$delivery->uuid} failed with {$response->status()}.",
            );
        });
    }

    /**
     * The last attempt has been spent.
     */
    public function failed(?Throwable $exception): void
    {
        $this->inTenantContext(function () use ($exception): void {
            $delivery = WebhookDelivery::query()->with('endpoint')->find($this->deliveryId);

            if ($delivery === null) {
                return;
            }

            $delivery->forceFill([
                'status' => DeliveryStatus::Failed,
                'error' => Str::limit($exception?->getMessage() ?? 'Delivery failed.', 500),
            ])->save();

            // Counted once per delivery rather than once per attempt: four tries
            // against one outage is one failure as far as retiring the endpoint
            // is concerned.
            $delivery->endpoint?->recordFailure();
        });
    }

    private function recordAttempt(
        WebhookDelivery $delivery,
        string $error,
        int $durationMs,
        ?int $status = null,
        ?string $body = null,
    ): void {
        $delivery->forceFill([
            'status' => DeliveryStatus::Retrying,
            'response_status' => $status,
            'response_body' => $body === null ? null : Str::limit($body, WebhookDelivery::RESPONSE_EXCERPT, ''),
            'duration_ms' => $durationMs,
            'error' => Str::limit($error, 500),
        ])->save();
    }

    /**
     * Whether retrying this status could ever succeed.
     */
    private function isPermanent(int $status): bool
    {
        if ($status === 408 || $status === 429) {
            return false;
        }

        return $status >= 400 && $status < 500;
    }
}
