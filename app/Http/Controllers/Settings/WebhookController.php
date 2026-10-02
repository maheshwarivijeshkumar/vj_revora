<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Webhooks\Actions\DispatchWebhookEvent;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Domain\Webhooks\Services\WebhookSignature;
use App\Http\Controllers\Controller;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Webhook endpoint management and the delivery log (§49, §50).
 */
final class WebhookController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/Webhooks', [
            'endpoints' => WebhookEndpoint::query()
                ->withCount('deliveries')
                ->orderByDesc('id')
                ->get()
                ->map(fn (WebhookEndpoint $endpoint): array => [
                    'id' => $endpoint->uuid,
                    'description' => $endpoint->description,
                    'url' => $endpoint->url,
                    // Shown in full: verifying a signature needs the secret, so
                    // a subscriber has to be able to read it back. It is behind
                    // the same permission as creating one.
                    'secret' => $endpoint->secret,
                    'events' => $endpoint->events,
                    'is_active' => $endpoint->is_active,
                    'is_deliverable' => $endpoint->isDeliverable(),
                    'consecutive_failures' => $endpoint->consecutive_failures,
                    'disabled_at' => $endpoint->disabled_at?->toIso8601String(),
                    'disabled_reason' => $endpoint->disabled_reason,
                    'last_delivered_at' => $endpoint->last_delivered_at?->toIso8601String(),
                    'deliveries_count' => $endpoint->deliveries_count,
                ])
                ->all(),
            'events' => WebhookEvent::options(),
            'deliveries' => $this->recentDeliveries(),
            'signature' => [
                'header' => WebhookSignature::HEADER,
                'tolerance' => WebhookSignature::TOLERANCE,
            ],
            'failureLimit' => WebhookEndpoint::FAILURE_LIMIT,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['required', 'string', 'max:255'],
            // HTTPS only: a signature proves who sent a payload, not that
            // nobody read it on the way, and these carry personal data (§88).
            'url' => ['required', 'url:https', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => [Rule::in(WebhookEvent::subscribableValues())],
        ], [
            'url.url' => 'Enter a full HTTPS URL, including the https:// prefix.',
            'events.required' => 'Choose at least one event to subscribe to.',
        ]);

        $endpoint = WebhookEndpoint::create([
            ...$validated,
            'created_by' => $request->user()?->id,
        ]);

        // The recorder redacts the signing secret, so this is the URL and the
        // subscription rather than a copy of the credential.
        app(AuditRecorder::class)->record(
            AuditAction::WebhookCreated,
            $endpoint,
            after: ['url' => $endpoint->url, 'events' => $endpoint->events],
        );

        return back()->with(
            'success',
            "“{$endpoint->description}” will now receive the events you selected.",
        );
    }

    public function update(Request $request, WebhookEndpoint $endpoint): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url:https', 'max:2048'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => [Rule::in(WebhookEvent::subscribableValues())],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $endpoint->fill($validated);

        // Re-enabling clears the automatic retirement as well as the pause,
        // otherwise the switch appears to do nothing.
        if ($request->boolean('is_active') && $endpoint->disabled_at !== null) {
            $endpoint->forceFill([
                'disabled_at' => null,
                'disabled_reason' => null,
                'consecutive_failures' => 0,
            ]);
        }

        app(AuditRecorder::class)->recordChange(AuditAction::WebhookUpdated, tap($endpoint)->save());

        return back()->with('success', 'Endpoint updated.');
    }

    public function destroy(WebhookEndpoint $endpoint): RedirectResponse
    {
        // Soft deleted, because the delivery log references it and "what were
        // we sending you last Tuesday" has to stay answerable (§54).
        $endpoint->delete();

        app(AuditRecorder::class)->record(
            AuditAction::WebhookDeleted,
            $endpoint,
            before: ['url' => $endpoint->url, 'events' => $endpoint->events],
        );

        return back()->with('success', "“{$endpoint->description}” has been removed.");
    }

    /**
     * Sends a sample payload so a subscriber can prove their verification works
     * before a real event depends on it.
     */
    public function test(WebhookEndpoint $endpoint): RedirectResponse
    {
        if (! $endpoint->isDeliverable()) {
            return back()->with('error', 'Re-enable this endpoint before testing it.');
        }

        app(DispatchWebhookEvent::class)->sendTest($endpoint);

        return back()->with('success', 'Test event queued. Its result appears in the log below.');
    }

    public function replay(WebhookDelivery $delivery): RedirectResponse
    {
        app(DispatchWebhookEvent::class)->replay($delivery);

        return back()->with('success', 'Queued for re-delivery.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentDeliveries(): array
    {
        return WebhookDelivery::query()
            ->with('endpoint:id,uuid,description')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (WebhookDelivery $delivery): array => [
                'id' => $delivery->uuid,
                'endpoint' => $delivery->endpoint?->description,
                'event' => $delivery->event->value,
                'status' => $delivery->status->value,
                'status_label' => $delivery->status->label(),
                'attempt' => $delivery->attempt,
                'response_status' => $delivery->response_status,
                'error' => $delivery->error,
                'duration_ms' => $delivery->duration_ms,
                'is_replay' => $delivery->replay_of_id !== null,
                // Only a settled delivery is worth replaying; one still
                // retrying will come round again on its own.
                'can_replay' => $delivery->status->isSettled(),
                'created_at' => $delivery->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
