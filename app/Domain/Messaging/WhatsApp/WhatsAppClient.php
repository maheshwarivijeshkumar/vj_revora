<?php

declare(strict_types=1);

namespace App\Domain\Messaging\WhatsApp;

use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Models\WhatsAppAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Sends messages through the WhatsApp Cloud API (§15).
 *
 * Two kinds: a pre-approved template, which may open a conversation at any
 * time, and free text, which WhatsApp only allows within 24 hours of the
 * customer's last message.
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages
 */
final class WhatsAppClient
{
    /** Graph error codes that mean the token or permission is gone. */
    private const AUTH_ERRORS = [190, 10, 200];

    /**
     * @param  list<string>  $bodyParameters  values for {{1}}, {{2}}… in the template body
     * @return string the message id (wamid)
     */
    public function sendTemplate(WhatsAppAccount $account, string $to, string $template, string $language, array $bodyParameters = []): string
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->digits($to),
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language],
            ],
        ];

        if ($bodyParameters !== []) {
            $payload['template']['components'] = [[
                'type' => 'body',
                'parameters' => array_map(fn (string $text): array => ['type' => 'text', 'text' => $text], $bodyParameters),
            ]];
        }

        return $this->send($account, $payload);
    }

    /**
     * @return string the message id (wamid)
     */
    public function sendText(WhatsAppAccount $account, string $to, string $text): string
    {
        return $this->send($account, [
            'messaging_product' => 'whatsapp',
            'to' => $this->digits($to),
            'type' => 'text',
            'text' => ['body' => $text, 'preview_url' => false],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(WhatsAppAccount $account, array $payload): string
    {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('services.whatsapp.api_version'),
            $account->phone_number_id,
        );

        try {
            $response = Http::withToken($account->access_token)->timeout(30)->post($url, $payload);
        } catch (ConnectionException $e) {
            throw new ProviderRequestException('Could not reach WhatsApp: '.$e->getMessage(), previous: $e);
        }

        if ($response->successful() && filled($response->json('messages.0.id'))) {
            return (string) $response->json('messages.0.id');
        }

        $error = (array) $response->json('error', []);
        $message = (string) ($error['error_data']['details'] ?? $error['message'] ?? 'HTTP '.$response->status());

        if ($response->status() === 401 || in_array($error['code'] ?? null, self::AUTH_ERRORS, true)) {
            throw new ProviderAuthException('WhatsApp refused the access token: '.$message);
        }

        throw new ProviderRequestException('WhatsApp did not send the message: '.$message);
    }

    /** WhatsApp wants the number as digits with the country code, no "+". */
    private function digits(string $phone): string
    {
        return (string) preg_replace('/\D/', '', $phone);
    }
}
