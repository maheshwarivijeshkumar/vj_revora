<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Mailboxes;

use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Domain\Messaging\Contracts\Mailbox;
use App\Domain\Messaging\DataObjects\IncomingEmail;
use App\Domain\Messaging\DataObjects\OutgoingEmail;
use App\Models\EmailAccount;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Throwable;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Message as ImapMessage;

/**
 * Any mailbox reachable over SMTP and IMAP: cPanel, Hostinger, Zoho, or Gmail
 * and Outlook with an app password.
 *
 * Credentials: smtp_host, smtp_port, smtp_encryption (ssl|tls|none),
 * imap_host, imap_port, imap_encryption, username, password.
 */
final class SmtpImapMailbox implements Mailbox
{
    /** Read no more than this per pass; the next pass picks up the rest. */
    private const MAX_PER_SYNC = 200;

    public function send(EmailAccount $account, OutgoingEmail $email): void
    {
        $credentials = $account->credentials;

        // Port 465 is implicit TLS; 587 upgrades with STARTTLS, which the
        // transport does on its own when the server offers it.
        $transport = new EsmtpTransport(
            (string) $credentials['smtp_host'],
            (int) $credentials['smtp_port'],
            ($credentials['smtp_encryption'] ?? 'ssl') === 'ssl',
        );
        $transport->setUsername((string) $credentials['username']);
        $transport->setPassword((string) $credentials['password']);

        try {
            $transport->send($email->toMime());
        } catch (TransportExceptionInterface $e) {
            // 535 is the SMTP code for rejected credentials.
            if (str_contains($e->getMessage(), '535') || stripos($e->getMessage(), 'authenticat') !== false) {
                throw new ProviderAuthException('The mail server rejected the username or password: '.$e->getMessage(), previous: $e);
            }

            throw new ProviderRequestException('Could not send through '.$credentials['smtp_host'].': '.$e->getMessage(), previous: $e);
        }
    }

    public function fetchInbox(EmailAccount $account, CarbonInterface $since): iterable
    {
        $credentials = $account->credentials;
        $encryption = $credentials['imap_encryption'] ?? 'ssl';

        try {
            $client = (new ClientManager)->make([
                'host' => (string) $credentials['imap_host'],
                'port' => (int) $credentials['imap_port'],
                'encryption' => $encryption === 'none' ? false : $encryption,
                'validate_cert' => true,
                'username' => (string) $credentials['username'],
                'password' => (string) $credentials['password'],
                'protocol' => 'imap',
            ]);
            $client->connect();

            $messages = $client->getFolderByPath('INBOX')
                ?->query()
                ->whereSince(Carbon::instance($since)->startOfDay())
                // Reading a reply here must not mark it read for the person
                // who also works this inbox in their mail app.
                ->leaveUnread()
                ->limit(self::MAX_PER_SYNC)
                ->get();
        } catch (AuthFailedException $e) {
            throw new ProviderAuthException('The mail server rejected the username or password: '.$e->getMessage(), previous: $e);
        } catch (Throwable $e) {
            throw new ProviderRequestException('Could not read the inbox at '.$credentials['imap_host'].': '.$e->getMessage(), previous: $e);
        }

        foreach ($messages ?? [] as $message) {
            $incoming = $this->toIncoming($message);

            // IMAP SINCE is day-granular, so trim to the exact moment here.
            if ($incoming !== null && $incoming->receivedAt->greaterThanOrEqualTo($since)) {
                yield $incoming;
            }
        }

        $client->disconnect();
    }

    private function toIncoming(ImapMessage $message): ?IncomingEmail
    {
        $from = $message->getFrom()->first();
        $messageId = trim((string) $message->getMessageId(), '<> ');

        if ($from === null || $messageId === '') {
            return null;
        }

        $text = $message->hasTextBody()
            ? $message->getTextBody()
            : trim(html_entity_decode(strip_tags($message->getHTMLBody())));

        try {
            $receivedAt = $message->getDate()->toDate();
        } catch (Throwable) {
            $receivedAt = now();
        }

        $inReplyTo = trim((string) $message->getInReplyTo(), '<> ');

        return new IncomingEmail(
            messageId: $messageId,
            fromAddress: mb_strtolower((string) $from->mail),
            fromName: $from->personal !== '' ? $from->personal : null,
            subject: (string) $message->getSubject(),
            textBody: $text,
            receivedAt: $receivedAt,
            inReplyTo: $inReplyTo === '' ? null : $inReplyTo,
        );
    }
}
