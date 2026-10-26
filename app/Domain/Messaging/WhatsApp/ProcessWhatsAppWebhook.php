<?php

declare(strict_types=1);

namespace App\Domain\Messaging\WhatsApp;

use App\Domain\Messaging\Actions\RecordLeadReply;
use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Enums\MessageDirection;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Tenant;
use App\Models\WhatsAppAccount;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Handles one verified WhatsApp webhook payload: inbound messages and
 * delivery statuses (§15).
 *
 * Meta sends every number's events to one URL, so each change is routed to
 * its workspace by the phone number id it is addressed to.
 */
final readonly class ProcessWhatsAppWebhook
{
    public function __construct(
        private TenantContext $context,
        private RecordLeadReply $recordReply,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $value = (array) ($change['value'] ?? []);
                $phoneNumberId = (string) ($value['metadata']['phone_number_id'] ?? '');

                $account = $this->context->withoutScoping(
                    fn (): ?WhatsAppAccount => WhatsAppAccount::query()->where('phone_number_id', $phoneNumberId)->first(),
                );

                if ($account === null) {
                    continue;
                }

                $tenant = $this->context->withoutScoping(fn (): ?Tenant => Tenant::query()->find($account->tenant_id));

                if ($tenant === null) {
                    continue;
                }

                $this->context->runAs($tenant, function () use ($value, $account): void {
                    foreach ($value['messages'] ?? [] as $message) {
                        $this->inbound((array) $message, $account);
                    }

                    foreach ($value['statuses'] ?? [] as $status) {
                        $this->status((array) $status);
                    }
                });
            }
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function inbound(array $message, WhatsAppAccount $account): void
    {
        $from = '+'.preg_replace('/\D/', '', (string) ($message['from'] ?? ''));

        $lead = Lead::query()->master()->where('phone_normalized', $from)->latest('id')->first();

        // Only conversations with known leads are kept.
        if ($lead === null) {
            return;
        }

        $body = match ($message['type'] ?? null) {
            'text' => (string) ($message['text']['body'] ?? ''),
            'button' => (string) ($message['button']['text'] ?? ''),
            'interactive' => (string) ($message['interactive']['button_reply']['title'] ?? $message['interactive']['list_reply']['title'] ?? ''),
            default => '['.($message['type'] ?? 'message').']',
        };

        try {
            $stored = Message::create([
                'lead_id' => $lead->id,
                'channel' => MessageChannel::WhatsApp,
                'direction' => MessageDirection::Inbound,
                'body' => $body,
                'from_address' => $from,
                'to_address' => $account->display_phone_number ?? $account->phone_number_id,
                'provider_message_id' => (string) ($message['id'] ?? ''),
                'status' => MessageStatus::Received,
                'received_at' => isset($message['timestamp']) ? Carbon::createFromTimestamp((int) $message['timestamp']) : now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Meta retries webhooks; a replay is a no-op (§58).
            return;
        }

        $this->recordReply->handle($lead, $stored);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function status(array $status): void
    {
        $new = MessageStatus::tryFrom((string) ($status['status'] ?? ''));
        $message = Message::query()
            ->where('channel', MessageChannel::WhatsApp->value)
            ->where('provider_message_id', (string) ($status['id'] ?? ''))
            ->first();

        if ($new === null || $message === null) {
            return;
        }

        // Statuses arrive out of order; only ever move forward.
        if ($new !== MessageStatus::Failed && $new->rank() <= $message->status->rank()) {
            return;
        }

        $message->forceFill([
            'status' => $new,
            'error' => $new === MessageStatus::Failed
                ? (string) ($status['errors'][0]['error_data']['details'] ?? $status['errors'][0]['title'] ?? 'Failed')
                : $message->error,
        ])->save();
    }
}
