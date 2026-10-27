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
 * Outlook and Microsoft 365, through Microsoft Graph.
 *
 * Graph's sendMail cannot set a Message-ID, so the CRM's own id travels in an
 * X- header; replies are matched on the sender's address in any case.
 *
 * @see https://learn.microsoft.com/en-us/graph/api/user-sendmail
 */
final class MicrosoftMailbox implements OAuthMailbox
{
    use RefreshesOAuthTokens;

    private const SCOPES = ['offline_access', 'openid', 'email', 'User.Read', 'Mail.Send', 'Mail.Read'];

    private const API = 'https://graph.microsoft.com/v1.0/me';

    private const MAX_PER_SYNC = 200;

    public function isConfigured(): bool
    {
        return filled(config('services.microsoft.client_id')) && filled(config('services.microsoft.client_secret'));
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return $this->authority().'/authorize?'.http_build_query([
            'client_id' => config('services.microsoft.client_id'),
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'response_mode' => 'query',
            'scope' => implode(' ', self::SCOPES),
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function connect(string $code, string $redirectUri): ConnectedMailbox
    {
        $token = $this->exchange($this->authority().'/token', [
            ...$this->client(),
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        $me = $this->decodeApi($this->call(
            fn () => Http::withToken((string) $token['access_token'])->timeout(30)->get(self::API, ['$select' => 'mail,userPrincipalName,displayName']),
            'Microsoft',
        ), 'Microsoft');

        return new ConnectedMailbox(
            email: mb_strtolower((string) ($me['mail'] ?? $me['userPrincipalName'])),
            name: $me['displayName'] ?? null,
            credentials: [
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600))->getTimestamp(),
            ],
        );
    }

    public function send(EmailAccount $account, OutgoingEmail $email): void
    {
        $headers = [['name' => 'X-Revora-Message-Id', 'value' => $email->messageId]];

        if ($email->unsubscribeUrl !== null) {
            $headers[] = ['name' => 'X-Unsubscribe-Url', 'value' => $email->unsubscribeUrl];
        }

        $token = $this->token($account);

        $this->decodeApi($this->call(fn () => Http::withToken($token)->timeout(30)->post(self::API.'/sendMail', [
            'message' => [
                'subject' => $email->subject,
                'body' => ['contentType' => 'HTML', 'content' => $email->htmlBody()],
                'toRecipients' => [['emailAddress' => array_filter(['address' => $email->to, 'name' => $email->toName])]],
                'internetMessageHeaders' => $headers,
            ],
            'saveToSentItems' => true,
        ]), 'Microsoft'), 'Microsoft');
    }

    public function fetchInbox(EmailAccount $account, CarbonInterface $since): iterable
    {
        $token = $this->token($account);
        $read = 0;

        $url = self::API.'/mailFolders/inbox/messages?'.http_build_query([
            '$filter' => 'receivedDateTime ge '.Carbon::instance($since)->utc()->format('Y-m-d\TH:i:s\Z'),
            '$orderby' => 'receivedDateTime',
            '$top' => 50,
            '$select' => 'internetMessageId,subject,from,body,receivedDateTime',
        ], '', '&', PHP_QUERY_RFC3986);

        while ($url !== null) {
            $page = $this->decodeApi($this->call(
                fn () => Http::withToken($token)
                    ->withHeaders(['Prefer' => 'outlook.body-content-type="text"'])
                    ->timeout(30)
                    ->get($url),
                'Microsoft',
            ), 'Microsoft');

            foreach ($page['value'] ?? [] as $message) {
                if (++$read > self::MAX_PER_SYNC) {
                    return;
                }

                $address = $message['from']['emailAddress'] ?? [];
                $messageId = trim((string) ($message['internetMessageId'] ?? ''), '<> ');

                if ($messageId === '' || empty($address['address'])) {
                    continue;
                }

                yield new IncomingEmail(
                    messageId: $messageId,
                    fromAddress: mb_strtolower((string) $address['address']),
                    fromName: $address['name'] ?? null,
                    subject: $message['subject'] ?? null,
                    textBody: (string) ($message['body']['content'] ?? ''),
                    receivedAt: Carbon::parse((string) $message['receivedDateTime']),
                );
            }

            $url = $page['@odata.nextLink'] ?? null;
        }
    }

    private function authority(): string
    {
        return 'https://login.microsoftonline.com/'.config('services.microsoft.tenant', 'common').'/oauth2/v2.0';
    }

    private function token(EmailAccount $account): string
    {
        return $this->accessToken($account, $this->authority().'/token', [
            ...$this->client(),
            'scope' => implode(' ', self::SCOPES),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function client(): array
    {
        return [
            'client_id' => (string) config('services.microsoft.client_id'),
            'client_secret' => (string) config('services.microsoft.client_secret'),
        ];
    }
}
