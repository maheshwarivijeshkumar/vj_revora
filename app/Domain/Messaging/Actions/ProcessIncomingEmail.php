<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Actions;

use App\Domain\Messaging\DataObjects\IncomingEmail;
use App\Domain\Messaging\Enums\MessageChannel;
use App\Domain\Messaging\Enums\MessageDirection;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Models\EmailAccount;
use App\Models\Lead;
use App\Models\Message;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Files an inbox email under the lead who sent it.
 *
 * Only email from a known lead is stored: a shared sales inbox also holds
 * invoices, newsletters and colleagues, none of which belong in the CRM.
 */
final readonly class ProcessIncomingEmail
{
    public function __construct(
        private RecordLeadReply $recordReply,
    ) {}

    public function handle(EmailAccount $account, IncomingEmail $email): ?Message
    {
        if ($email->fromAddress === mb_strtolower($account->email)) {
            return null;
        }

        $known = Message::query()
            ->where('channel', MessageChannel::Email->value)
            ->where('provider_message_id', $email->messageId)
            ->exists();

        if ($known) {
            return null;
        }

        $lead = $this->lead($email);

        if ($lead === null) {
            return null;
        }

        try {
            $message = Message::create([
                'lead_id' => $lead->id,
                'channel' => MessageChannel::Email,
                'direction' => MessageDirection::Inbound,
                'email_account_id' => $account->id,
                'subject' => $email->subject,
                'body' => $email->freshText() !== '' ? $email->freshText() : $email->textBody,
                'from_address' => $email->fromAddress,
                'to_address' => $account->email,
                'provider_message_id' => $email->messageId,
                'in_reply_to' => $email->inReplyTo,
                'status' => MessageStatus::Received,
                'received_at' => $email->receivedAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Another sync of the same mailbox stored it first.
            return null;
        }

        $this->recordReply->handle($lead, $message);

        return $message;
    }

    /**
     * The lead this email is from: by the thread it answers first, then by
     * the sender's address.
     */
    private function lead(IncomingEmail $email): ?Lead
    {
        if ($email->inReplyTo !== null) {
            $leadId = Message::query()
                ->where('channel', MessageChannel::Email->value)
                ->where('provider_message_id', $email->inReplyTo)
                ->value('lead_id');

            if ($leadId !== null) {
                return Lead::query()->whereKey($leadId)->first();
            }
        }

        return Lead::query()
            ->master()
            ->where('email_normalized', $email->fromAddress)
            ->latest('id')
            ->first();
    }
}
