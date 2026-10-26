<?php

declare(strict_types=1);

namespace App\Domain\Messaging\DataObjects;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * An email ready to send, independent of how the mailbox sends it.
 */
final readonly class OutgoingEmail
{
    public function __construct(
        public string $messageId,
        public string $fromAddress,
        public ?string $fromName,
        public string $to,
        public ?string $toName,
        public string $subject,
        public string $textBody,
        public ?string $inReplyTo = null,
        public ?string $unsubscribeUrl = null,
    ) {}

    /** HTML twin of the text body: escaped, with line breaks kept. */
    public function htmlBody(): string
    {
        return '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5">'
            .nl2br(e($this->textBody))
            .'</div>';
    }

    /**
     * The message as a MIME object, for transports that send raw RFC 822
     * (SMTP, the Gmail API).
     */
    public function toMime(): Email
    {
        $email = (new Email)
            ->from(new Address($this->fromAddress, $this->fromName ?? ''))
            ->to(new Address($this->to, $this->toName ?? ''))
            ->subject($this->subject)
            ->text($this->textBody)
            ->html($this->htmlBody());

        $headers = $email->getHeaders();
        $headers->addIdHeader('Message-ID', $this->bareId($this->messageId));

        if ($this->inReplyTo !== null) {
            $headers->addIdHeader('In-Reply-To', $this->bareId($this->inReplyTo));
            $headers->addIdHeader('References', $this->bareId($this->inReplyTo));
        }

        // One-click unsubscribe, which Gmail and Yahoo require of senders
        // that mail in volume (RFC 8058).
        if ($this->unsubscribeUrl !== null) {
            $headers->addTextHeader('List-Unsubscribe', '<'.$this->unsubscribeUrl.'>');
            $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }

        return $email;
    }

    private function bareId(string $id): string
    {
        return trim($id, '<> ');
    }
}
