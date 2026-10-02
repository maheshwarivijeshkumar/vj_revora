<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Domain\Webhooks\Actions\DispatchWebhookEvent;
use App\Domain\Webhooks\Enums\DeliveryStatus;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Domain\Webhooks\Services\WebhookSignature;
use App\Jobs\DeliverWebhook;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Webhook delivery
|--------------------------------------------------------------------------
|
| §49. Signed, retried with backoff, logged per attempt, replayable, and the
| endpoint retires itself once it is clearly not coming back.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);

    // The test queue connection is `sync`, so without this every dispatch
    // would deliver inline and these tests could never separate "queued" from
    // "delivered". Each test runs the job itself, deliberately, via deliver().
    Queue::fake();

    $this->endpoint = WebhookEndpoint::factory()->create(['tenant_id' => $this->tenant->id]);
});

function deliver(WebhookDelivery $delivery): void
{
    (new DeliverWebhook($delivery->id))->handle();
}

function dispatchEvent(WebhookEvent $event = WebhookEvent::LeadCreated, array $data = ['id' => 'abc']): WebhookDelivery
{
    return app(DispatchWebhookEvent::class)->handle($event, $data)->first();
}

// --- Fan-out ----------------------------------------------------------------

it('queues one delivery per subscribed endpoint', function (): void {
    WebhookEndpoint::factory()
        ->subscribedTo([WebhookEvent::LeadCreated])
        ->create(['tenant_id' => $this->tenant->id]);

    WebhookEndpoint::factory()
        ->subscribedTo([WebhookEvent::DealWon])
        ->create(['tenant_id' => $this->tenant->id]);

    $deliveries = app(DispatchWebhookEvent::class)->handle(WebhookEvent::LeadCreated, ['id' => 'x']);

    // The factory default also subscribes to lead.created, so two of the three
    // endpoints are listening.
    expect($deliveries)->toHaveCount(2);
    Queue::assertPushed(DeliverWebhook::class, 2);
});

it('does not log a delivery when nobody is listening', function (): void {
    $deliveries = app(DispatchWebhookEvent::class)->handle(WebhookEvent::DealLost, ['id' => 'x']);

    // A row per event per workspace would make the log unreadable for the
    // workspaces that do subscribe.
    expect($deliveries)->toBeEmpty()
        ->and(WebhookDelivery::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('skips a paused and a retired endpoint', function (): void {
    WebhookEndpoint::factory()->paused()->create(['tenant_id' => $this->tenant->id]);
    WebhookEndpoint::factory()->disabled()->create(['tenant_id' => $this->tenant->id]);

    expect(app(DispatchWebhookEvent::class)->handle(WebhookEvent::LeadCreated, ['id' => 'x']))
        ->toHaveCount(1);
});

it('never delivers to another workspace\'s endpoint', function (): void {
    Queue::fake();

    $other = Tenant::factory()->create();
    app(TenantContext::class)->runAs(
        $other,
        fn () => WebhookEndpoint::factory()->create(['tenant_id' => $other->id]),
    );

    expect(app(DispatchWebhookEvent::class)->handle(WebhookEvent::LeadCreated, ['id' => 'x']))
        ->toHaveCount(1);
});

it('matches an event name exactly rather than by prefix', function (): void {
    WebhookEndpoint::factory()
        ->subscribedTo([WebhookEvent::LeadUpdated])
        ->create(['tenant_id' => $this->tenant->id]);

    // lead.updated must not be matched by a lead.update* style comparison.
    expect(app(DispatchWebhookEvent::class)->handle(WebhookEvent::LeadQualified, ['id' => 'x']))
        ->toBeEmpty();
});

// --- The envelope -----------------------------------------------------------

it('wraps the entity in an envelope the subscriber can deduplicate on', function (): void {
    $delivery = dispatchEvent(WebhookEvent::LeadCreated, ['id' => 'lead-uuid', 'email' => 'a@b.example']);

    expect($delivery->payload['event'])->toBe('lead.created')
        ->and($delivery->payload['api_version'])->toBe('v1')
        ->and($delivery->payload['data']['email'])->toBe('a@b.example')
        // Identifies the event, not the entity, so a retry is recognisable.
        ->and($delivery->payload['id'])->not->toBe('lead-uuid')
        ->and($delivery->payload['occurred_at'])->not->toBeNull();
});

// --- Signing ----------------------------------------------------------------

it('signs the exact bytes it sends', function (): void {
    Http::fake(['*' => Http::response('ok', 200)]);

    deliver(dispatchEvent());

    Http::assertSent(function ($request): bool {
        $header = $request->header(WebhookSignature::HEADER)[0];

        // Verified against the body as transmitted: signing a re-encoded copy
        // would produce a signature the subscriber cannot reproduce.
        return WebhookSignature::verify($header, $request->body(), $this->endpoint->secret);
    });
});

it('rejects a signature whose timestamp has been moved', function (): void {
    $payload = '{"event":"lead.created"}';
    $secret = 'whsec_test';
    $header = WebhookSignature::header($payload, $secret, 1_700_000_000);

    // The timestamp is inside the signed string, so a captured request cannot
    // be made to look fresh.
    $tampered = preg_replace('/^t=\d+/', 't='.(1_700_000_000 + 10), $header);

    expect(WebhookSignature::verify($tampered, $payload, $secret, 1_700_000_000 + 10))->toBeFalse();
});

it('rejects a signature that is outside the tolerance window', function (): void {
    $payload = '{"event":"lead.created"}';
    $header = WebhookSignature::header($payload, 'whsec_test', 1_700_000_000);

    expect(WebhookSignature::verify($header, $payload, 'whsec_test', 1_700_000_000 + 60))->toBeTrue()
        ->and(WebhookSignature::verify($header, $payload, 'whsec_test', 1_700_000_000 + 3600))->toBeFalse();
});

it('rejects a malformed or empty signature header', function (): void {
    $payload = '{}';

    expect(WebhookSignature::verify('', $payload, 'whsec_test'))->toBeFalse()
        ->and(WebhookSignature::verify('nonsense', $payload, 'whsec_test'))->toBeFalse()
        ->and(WebhookSignature::verify('t=abc,v1=def', $payload, 'whsec_test'))->toBeFalse()
        ->and(WebhookSignature::verify('t='.time().',v1=', $payload, 'whsec_test'))->toBeFalse();
});

it('sends the headers a subscriber needs to deduplicate and route', function (): void {
    Http::fake(['*' => Http::response('ok', 200)]);

    $delivery = dispatchEvent(WebhookEvent::LeadCreated);
    deliver($delivery);

    Http::assertSent(fn ($request): bool => $request->header('X-Revora-Delivery')[0] === $delivery->uuid
        && $request->header('X-Revora-Event')[0] === 'lead.created'
        && $request->hasHeader('Content-Type', 'application/json'));
});

// --- Recording an attempt ---------------------------------------------------

it('records a successful delivery and clears the failure streak', function (): void {
    Http::fake(['*' => Http::response('thanks', 202)]);

    $this->endpoint->forceFill(['consecutive_failures' => 3])->save();

    $delivery = dispatchEvent();
    deliver($delivery);

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Delivered)
        ->and($delivery->response_status)->toBe(202)
        ->and($delivery->response_body)->toBe('thanks')
        ->and($delivery->delivered_at)->not->toBeNull()
        ->and($delivery->duration_ms)->not->toBeNull()
        ->and($this->endpoint->refresh()->consecutive_failures)->toBe(0)
        ->and($this->endpoint->last_delivered_at)->not->toBeNull();
});

it('marks a 5xx for retry and keeps the response for the log', function (): void {
    Http::fake(['*' => Http::response('upstream exploded', 503)]);

    $delivery = dispatchEvent();

    // Rethrown so the queue applies the backoff rather than calling it done.
    expect(fn () => deliver($delivery))->toThrow(RuntimeException::class);

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Retrying)
        ->and($delivery->response_status)->toBe(503)
        ->and($delivery->response_body)->toBe('upstream exploded')
        ->and($delivery->error)->toContain('503');
});

it('settles a 4xx immediately instead of burning its remaining attempts', function (): void {
    Http::fake(['*' => Http::response('bad signature config', 401)]);

    $delivery = dispatchEvent();
    deliver($delivery);

    // A wrong URL or an unconfigured secret will not fix itself on a retry.
    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Failed)
        ->and($this->endpoint->refresh()->consecutive_failures)->toBe(1);
});

it('still retries a 429 and a 408', function (): void {
    foreach ([429, 408] as $status) {
        Http::fake(['*' => Http::response('slow down', $status)]);

        $delivery = dispatchEvent();

        expect(fn () => deliver($delivery))->toThrow(RuntimeException::class);
        expect($delivery->refresh()->status)->toBe(DeliveryStatus::Retrying);
    }
});

it('truncates a response body so one endpoint cannot fill the table', function (): void {
    Http::fake(['*' => Http::response(str_repeat('x', 10_000), 200)]);

    $delivery = dispatchEvent();
    deliver($delivery);

    expect(mb_strlen((string) $delivery->refresh()->response_body))
        ->toBeLessThanOrEqual(WebhookDelivery::RESPONSE_EXCERPT);
});

it('records a connection failure and rethrows for the backoff', function (): void {
    Http::fake(fn () => throw new ConnectionException('Could not resolve host'));

    $delivery = dispatchEvent();

    expect(fn () => deliver($delivery))->toThrow(ConnectionException::class);

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Retrying)
        ->and($delivery->error)->toContain('resolve host')
        ->and($delivery->response_status)->toBeNull();
});

it('does not deliver to an endpoint retired while the attempt was queued', function (): void {
    Http::fake();

    $delivery = dispatchEvent();
    $this->endpoint->forceFill(['disabled_at' => now()])->save();

    deliver($delivery);

    // Recorded rather than silently dropped, so the log explains the gap.
    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery->error)->toContain('disabled');
    Http::assertNothingSent();
});

// --- Retry policy -----------------------------------------------------------

it('backs off exponentially with jitter', function (): void {
    $backoff = (new DeliverWebhook(1))->backoff();

    expect($backoff)->toHaveCount(3)
        ->and($backoff[0])->toBeGreaterThanOrEqual(10)->toBeLessThanOrEqual(15)
        ->and($backoff[1])->toBeGreaterThanOrEqual(60)->toBeLessThanOrEqual(75)
        // Jitter, so an outage's worth of deliveries do not return as a herd.
        ->and($backoff[2])->toBeGreaterThanOrEqual(300)->toBeLessThanOrEqual(360);
});

it('counts one failure per delivery, not one per attempt', function (): void {
    Http::fake();

    $delivery = dispatchEvent();

    $job = new DeliverWebhook($delivery->id);
    $job->failed(new RuntimeException('gave up'));

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Failed)
        ->and($delivery->error)->toBe('gave up')
        // Four attempts against one outage is one failure for the endpoint.
        ->and($this->endpoint->refresh()->consecutive_failures)->toBe(1);
});

// --- Auto-disable -----------------------------------------------------------

it('retires an endpoint after a long run of failures', function (): void {
    $this->endpoint->forceFill([
        'consecutive_failures' => WebhookEndpoint::FAILURE_LIMIT - 1,
    ])->save();

    $this->endpoint->recordFailure();

    $endpoint = $this->endpoint->refresh();

    expect($endpoint->disabled_at)->not->toBeNull()
        ->and($endpoint->isDeliverable())->toBeFalse()
        // The reason is on the record, so nobody has to guess why it stopped.
        ->and($endpoint->disabled_reason)->toContain((string) WebhookEndpoint::FAILURE_LIMIT);
});

it('does not retire an endpoint that recovers mid-streak', function (): void {
    $this->endpoint->forceFill([
        'consecutive_failures' => WebhookEndpoint::FAILURE_LIMIT - 1,
    ])->save();

    $this->endpoint->recordSuccess();
    $this->endpoint->recordFailure();

    // Consecutive, so one bad day does not count towards a disable forever.
    expect($this->endpoint->refresh()->consecutive_failures)->toBe(1)
        ->and($this->endpoint->disabled_at)->toBeNull();
});

// --- Replay -----------------------------------------------------------------

it('replays a delivery as a new attempt without destroying the original', function (): void {
    Http::fake(['*' => Http::response('nope', 500)]);

    $original = dispatchEvent();
    expect(fn () => deliver($original))->toThrow(RuntimeException::class);

    Http::fake(['*' => Http::response('ok', 200)]);

    $replay = app(DispatchWebhookEvent::class)->replay($original->refresh());

    expect($replay->id)->not->toBe($original->id)
        ->and($replay->replay_of_id)->toBe($original->id)
        // The same bytes, so a replay reproduces what the subscriber was told
        // rather than re-serialising a record that has since changed.
        ->and($replay->payload)->toBe($original->payload)
        ->and($replay->status)->toBe(DeliveryStatus::Pending)
        // The original is evidence of what happened and stays as it was.
        ->and($original->refresh()->response_status)->toBe(500);

    Queue::assertPushed(DeliverWebhook::class);
});

// --- The queue contract -----------------------------------------------------

it('carries the workspace on the job so the worker can restore it', function (): void {
    dispatchEvent();

    Queue::assertPushed(
        DeliverWebhook::class,
        // A queue worker is shared by every tenant and keeps nothing from the
        // request that queued the job (ADR-010).
        fn (DeliverWebhook $job): bool => $job->tenantId === $this->tenant->id
            && $job->queue === 'webhooks',
    );
});

it('delivers correctly when nothing is bound, as on a worker', function (): void {
    Http::fake(['*' => Http::response('ok', 200)]);

    $delivery = dispatchEvent();

    // Built while the workspace is still bound, the way a real dispatch does it.
    $job = new DeliverWebhook($delivery->id);

    // The state a real worker starts in: no tenant, so the global scope would
    // otherwise fail closed and the job would find nothing.
    app(TenantContext::class)->forget();

    $job->handle();

    expect($delivery->refresh()->status)->toBe(DeliveryStatus::Delivered);
});
