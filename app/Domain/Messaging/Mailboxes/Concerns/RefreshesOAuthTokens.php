<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Mailboxes\Concerns;

use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Models\EmailAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Keeps an OAuth mailbox's access token fresh.
 *
 * Google and Microsoft both issue hour-long access tokens with a refresh
 * token; the refresh happens just before use, and a refused refresh (revoked
 * consent, changed password) asks the user to reconnect.
 */
trait RefreshesOAuthTokens
{
    /**
     * @param  array<string, string>  $client  client_id, client_secret, plus any extra form fields
     */
    protected function accessToken(EmailAccount $account, string $tokenUrl, array $client): string
    {
        $credentials = $account->credentials;

        if (($credentials['expires_at'] ?? 0) > now()->addMinute()->getTimestamp()) {
            return (string) $credentials['access_token'];
        }

        if (empty($credentials['refresh_token'])) {
            throw new ProviderAuthException('This mailbox needs to be reconnected: there is no refresh token.');
        }

        $token = $this->exchange($tokenUrl, [
            ...$client,
            'grant_type' => 'refresh_token',
            'refresh_token' => (string) $credentials['refresh_token'],
        ]);

        $account->forceFill(['credentials' => [
            ...$credentials,
            'access_token' => $token['access_token'],
            // Microsoft rotates refresh tokens; Google usually does not.
            'refresh_token' => $token['refresh_token'] ?? $credentials['refresh_token'],
            'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600))->getTimestamp(),
        ]])->save();

        return (string) $token['access_token'];
    }

    /**
     * @param  array<string, string>  $form
     * @return array<string, mixed>
     */
    protected function exchange(string $tokenUrl, array $form): array
    {
        try {
            $response = Http::asForm()->timeout(30)->post($tokenUrl, $form);
        } catch (ConnectionException $e) {
            throw new ProviderRequestException('Could not reach the sign-in service: '.$e->getMessage(), previous: $e);
        }

        if ($response->successful() && filled($response->json('access_token'))) {
            return (array) $response->json();
        }

        $error = (string) ($response->json('error_description') ?? $response->json('error') ?? 'HTTP '.$response->status());

        // invalid_grant: the refresh token was revoked or expired.
        if ($response->status() === 400 || $response->status() === 401) {
            throw new ProviderAuthException('Sign-in was refused: '.$error);
        }

        throw new ProviderRequestException('Sign-in failed: '.$error);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeApi(Response $response, string $service): array
    {
        if ($response->successful()) {
            return (array) $response->json();
        }

        $message = (string) ($response->json('error.message') ?? 'HTTP '.$response->status());

        if (in_array($response->status(), [401, 403], true)) {
            throw new ProviderAuthException("{$service} refused access: {$message}");
        }

        throw new ProviderRequestException("{$service} request failed: {$message}");
    }

    /**
     * @param  callable(): Response  $request
     */
    protected function call(callable $request, string $service): Response
    {
        try {
            return $request();
        } catch (ConnectionException $e) {
            throw new ProviderRequestException("Could not reach {$service}: ".$e->getMessage(), previous: $e);
        }
    }
}
