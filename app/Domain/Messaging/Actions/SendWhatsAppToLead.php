<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Enums\MessageDirection;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Domain\Messaging\Exceptions\CannotMessageLead;
use App\Domain\Messaging\WhatsApp\WhatsAppClient;
use App\Models\Lead;
use App\Models\Message;
use App\Models\WhatsAppAccount;

/**
 * Sends a WhatsApp message to a lead and records it (§15).
 *
 * Every path — the automatic welcome, a person typing on the lead page —
 * comes through here, so opt-out and the 24-hour rule are enforced once.
 */
final readonly class SendWhatsAppToLead
{
    /** WhatsApp's customer-service window. */
    public const WINDOW_HOURS = 24;

    public function __construct(
        private WhatsAppClient $client,
    ) {}

    /**
     * Free text, allowed only inside the 24-hour window.
     *
     * @throws CannotMessageLead|ProviderAuthException|ProviderRequestException
     */
    public function text(Lead $lead, string $text, ?int $userId = null): Message
    {
        [$account, $to] = $this->prepare($lead);

        if (! $this->windowOpen($lead)) {
            throw CannotMessageLead::windowClosed();
        }

        $wamid = $this->guard($account, fn (): string => $this->client->sendText($account, $to, $text));

        return $this->record($lead, $account, $to, $text, $wamid, $userId);
    }

    /**
     * The workspace's approved welcome template, which may start a
     * conversation at any time. The lead's first name fills {{1}}.
     *
     * @throws CannotMessageLead|ProviderAuthException|ProviderRequestException
     */
    public function template(Lead $lead, ?int $userId = null): Message
    {
        [$account, $to] = $this->prepare($lead);

        if (blank($account->welcome_template)) {
            throw new CannotMessageLead('Set the approved template name in Settings → WhatsApp first.');
        }

        $wamid = $this->guard($account, fn (): string => $this->client->sendTemplate(
            $account,
            $to,
            (string) $account->welcome_template,
            $account->template_language,
            [$lead->first_name ?: 'there'],
        ));

        return $this->record($lead, $account, $to, "Template: {$account->welcome_template}", $wamid, $userId);
    }

    /** Whether the lead wrote to us on WhatsApp within the last 24 hours. */
    public function windowOpen(Lead $lead): bool
    {
        return Message::query()
            ->where('lead_id', $lead->id)
            ->where('channel', MessageChannel::WhatsApp->value)
            ->where('direction', MessageDirection::Inbound->value)
            ->where('received_at', '>=', now()->subHours(self::WINDOW_HOURS))
            ->exists();
    }

    /**
     * @return array{0: WhatsAppAccount, 1: string}
     */
    private function prepare(Lead $lead): array
    {
        if ($lead->whatsapp_opted_out_at !== null) {
            throw CannotMessageLead::optedOut('WhatsApp');
        }

        $to = Lead::normalisePhone($lead->phone);

        if ($to === null) {
            throw CannotMessageLead::noAddress('whatsapp');
        }

        $account = WhatsAppAccount::query()->first();

        if ($account === null || $account->status === ConnectionStatus::NeedsReconnect) {
            throw CannotMessageLead::noSender('whatsapp');
        }

        return [$account, $to];
    }

    /**
     * @param  callable(): string  $send
     */
    private function guard(WhatsAppAccount $account, callable $send): string
    {
        try {
            return $send();
        } catch (ProviderAuthException $e) {
            $account->forceFill(['status' => ConnectionStatus::NeedsReconnect, 'last_error' => $e->getMessage()])->save();

            throw $e;
        }
    }

    private function record(Lead $lead, WhatsAppAccount $account, string $to, string $body, string $wamid, ?int $userId): Message
    {
        return Message::create([
            'lead_id' => $lead->id,
            'channel' => MessageChannel::WhatsApp,
            'direction' => MessageDirection::Outbound,
            'body' => $body,
            'from_address' => $account->display_phone_number ?? $account->phone_number_id,
            'to_address' => $to,
            'provider_message_id' => $wamid,
            'status' => MessageStatus::Sent,
            'user_id' => $userId,
            'sent_at' => now(),
        ]);
    }
}
