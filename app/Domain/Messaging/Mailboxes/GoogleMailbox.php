<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Mailboxes;

use App\Domain\Messaging\Contracts\OAuthMailbox;
use App\Domain\Messaging\DataObjects\ConnectedMailbox;
use App\Domain\Messaging\DataObjects\IncomingEmail;
use App\Domain\Messaging\DataObjects\OutgoingEmail;
use App\Domain\Messaging\Mailboxes\Concerns\RefreshesOAuthTokens;
use App\Models\EmailAccount;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Gmail and Google Workspace, through the Gmail API.
 *
 * Asks only for send and read-only scopes: the CRM never deletes, labels or
 * archives anything in the user's mailbox.
 *
 * @see https://developers.google.com/gmail/api/reference/rest
 */
final class GoogleMailbox implements OAuthMailbox
{
    use RefreshesOAuthTokens;

    private const SCOPES = [
        'openid',
        'email',
        'https://www.googleapis.com/auth/gmail.send',
        'https://www.googleapis.com/auth/gmail.readonly',
    ];

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const API = 'https://gmail.googleapis.com/gmail/v1/users/me';

    private const MAX_PER_SYNC = 200;

    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            // offline + consent is what makes Google return a refresh token.
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function connect(string $code, string $redirectUri): ConnectedMailbox
    {
        $token = $this->exchange(self::TOKEN_URL, [
            ...$this->client(),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        $profile = $this->decodeApi($this->call(
            fn () => Http::withToken((string) $token['access_token'])->timeout(30)->get(self::API.'/profile'),
            'Gmail',
        ), 'Gmail');

        return new ConnectedMailbox(
            email: mb_strtolower((string) $profile['emailAddress']),
            name: null,
            credentials: [
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600))->getTimestamp(),
            ],
        );
    }

    public function send(EmailAccount $account, OutgoingEmail $email): void
    {
        $raw = rtrim(strtr(base64_encode($email->toMime()->toString()), '+/', '-_'), '=');
        $token = $this->token($account);

        $this->decodeApi($this->call(
            fn () => Http::withToken($token)->timeout(30)->post(self::API.'/messages/send', ['raw' => $raw]),
            'Gmail',
        ), 'Gmail');
    }

    public function fetchInbox(EmailAccount $account, CarbonInterface $since): iterable
    {
        $token = $this->token($account);
        $pageToken = null;
        $read = 0;

        do {
            $list = $this->decodeApi($this->call(fn () => Http::withToken($token)->timeout(30)->get(self::API.'/messages', array_filter([
                'q' => 'in:inbox after:'.$since->getTimestamp(),
                'maxResults' => 100,
                'pageToken' => $pageToken,
            ])), 'Gmail'), 'Gmail');

            foreach ($list['messages'] ?? [] as $ref) {
                if (++$read > self::MAX_PER_SYNC) {
                    return;
                }

                $message = $this->decodeApi($this->call(
                    fn () => Http::withToken($token)->timeout(30)->get(self::API.'/messages/'.$ref['id'], ['format' => 'full']),
                    'Gmail',
                ), 'Gmail');

                $incoming = $this->toIncoming($message);

                if ($incoming !== null) {
                    yield $incoming;
                }
            }

            $pageToken = $list['nextPageToken'] ?? null;
        } while ($pageToken !== null);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function toIncoming(array $message): ?IncomingEmail
    {
        $headers = [];

        foreach ($message['payload']['headers'] ?? [] as $header) {
            $headers[mb_strtolower((string) $header['name'])] = (string) $header['value'];
        }

        $messageId = trim($headers['message-id'] ?? '', '<> ');
        [$fromName, $fromAddress] = $this->parseAddress($headers['from'] ?? '');

        if ($messageId === '' || $fromAddress === null) {
            return null;
        }

        $inReplyTo = trim($headers['in-reply-to'] ?? '', '<> ');

        return new IncomingEmail(
            messageId: $messageId,
            fromAddress: $fromAddress,
            fromName: $fromName,
            subject: $headers['subject'] ?? null,
            textBody: $this->body((array) ($message['payload'] ?? [])),
            receivedAt: Carbon::createFromTimestampMs((int) ($message['internalDate'] ?? now()->getTimestampMs())),
            inReplyTo: $inReplyTo === '' ? null : $inReplyTo,
        );
    }

    /**
     * The plain-text part, or the HTML part with its tags stripped.
     *
     * @param  array<string, mixed>  $part
     */
    private function body(array $part, string $want = 'text/plain'): string
    {
        if (($part['mimeType'] ?? '') === $want && isset($part['body']['data'])) {
            return (string) base64_decode(strtr((string) $part['body']['data'], '-_', '+/'));
        }

        foreach ($part['parts'] ?? [] as $child) {
            $found = $this->body((array) $child, $want);

            if ($found !== '') {
                return $found;
            }
        }

        if ($want === 'text/plain' && ($part['mimeType'] ?? '') !== 'text/html') {
            return trim(html_entity_decode(strip_tags($this->body($part, 'text/html'))));
        }

        return '';
    }

    /**
     * "Amara Okafor <amara@example.com>" → [name, address].
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function parseAddress(string $value): array
    {
        if (preg_match('/^\s*"?([^"<]*)"?\s*<([^>]+)>/', $value, $m) === 1) {
            return [trim($m[1]) === '' ? null : trim($m[1]), mb_strtolower(trim($m[2]))];
        }

        $address = trim($value);

        return [null, filter_var($address, FILTER_VALIDATE_EMAIL) ? mb_strtolower($address) : null];
    }

    private function token(EmailAccount $account): string
    {
        return $this->accessToken($account, self::TOKEN_URL, $this->client());
    }

    /**
     * @return array<string, string>
     */
    private function client(): array
    {
        return [
            'client_id' => (string) config('services.google.client_id'),
            'client_secret' => (string) config('services.google.client_secret'),
        ];
    }
}
