<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Domain\Messaging\DataObjects\OutgoingEmail;
use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Enums\MessageDirection;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Domain\Messaging\Exceptions\CannotMessageLead;
use App\Domain\Messaging\Mailboxes;
use App\Models\EmailAccount;
use App\Models\Lead;
use App\Models\Message;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Sends one email to a lead and records it in the conversation (§25).
 *
 * Every path that emails a lead — the sequence, a person composing one —
 * comes through here, so the opt-out check cannot be skipped (§88).
 */
final readonly class SendEmailToLead
{
    public function __construct(
        private Mailboxes $mailboxes,
    ) {}

    /**
     * @param  array{enrollment_id?: int, step?: int, user_id?: int}  $context
     *
     * @throws CannotMessageLead when the lead cannot be emailed at all.
     * @throws ProviderAuthException|ProviderRequestException when sending failed.
     */
    public function handle(Lead $lead, EmailAccount $account, string $subject, string $body, array $context = []): Message
    {
        if ($lead->email_opted_out_at !== null) {
            throw CannotMessageLead::optedOut('email');
        }

        if (blank($lead->email)) {
            throw CannotMessageLead::noAddress('email');
        }

        // Threads the conversation: replies to the last email we sent.
        $previous = Message::query()
            ->where('lead_id', $lead->id)
            ->where('channel', MessageChannel::Email->value)
            ->where('direction', MessageDirection::Outbound->value)
            ->whereNotNull('provider_message_id')
            ->latest('id')
            ->value('provider_message_id');

        $domain = Str::after($account->email, '@') ?: 'revora.local';
        $email = new OutgoingEmail(
            messageId: Str::uuid()->toString().'@'.$domain,
            fromAddress: $account->email,
            fromName: $account->from_name,
            to: (string) $lead->email,
            toName: $lead->displayName(),
            subject: $subject,
            textBody: $body."\n\n--\nDon't want these emails? Unsubscribe: ".$this->unsubscribeUrl($lead),
            inReplyTo: $previous,
            unsubscribeUrl: $this->unsubscribeUrl($lead),
        );

        try {
            $this->mailboxes->for($account->provider)->send($account, $email);
        } catch (ProviderAuthException $e) {
            $account->forceFill(['status' => ConnectionStatus::NeedsReconnect, 'last_error' => $e->getMessage()])->save();

            throw $e;
        }

        return Message::create([
            'lead_id' => $lead->id,
            'channel' => MessageChannel::Email,
            'direction' => MessageDirection::Outbound,
            'email_account_id' => $account->id,
            'follow_up_enrollment_id' => $context['enrollment_id'] ?? null,
            'step_position' => $context['step'] ?? null,
            'subject' => $subject,
            'body' => $body,
            'from_address' => $account->email,
            'to_address' => $lead->email,
            'provider_message_id' => $email->messageId,
            'in_reply_to' => $previous,
            'status' => MessageStatus::Sent,
            'user_id' => $context['user_id'] ?? null,
            'sent_at' => now(),
        ]);
    }

    /** A signed link: only someone holding the email can opt this lead out. */
    public function unsubscribeUrl(Lead $lead): string
    {
        return URL::signedRoute('unsubscribe', ['lead' => $lead->uuid]);
    }
}
