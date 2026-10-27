<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Providers;

use App\Domain\Integrations\Contracts\LeadAdsProvider;
use App\Domain\Integrations\DataObjects\DiscoveredAccount;
use App\Domain\Integrations\DataObjects\ProviderLead;
use App\Domain\Integrations\Enums\LeadAdsPlatform;
use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Models\IntegrationConnection;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Facebook Lead Ads, which also carries Instagram lead ads (§12).
 *
 * One connection per Facebook Page, holding that Page's access token: Meta
 * recommends Page tokens for lead retrieval, and a Page token derived from a
 * long-lived user token does not expire on a timer.
 *
 * @see https://developers.facebook.com/docs/marketing-api/guides/lead-ads/retrieving
 */
final class MetaLeadAdsProvider implements LeadAdsProvider
{
    /** What Meta lists as needed to read leads and their ad-level data. */
    private const SCOPES = [
        'ads_management',
        'leads_retrieval',
        'pages_show_list',
        'pages_read_engagement',
        'pages_manage_ads',
    ];

    private const LEAD_FIELDS = 'id,created_time,field_data,form_id,ad_id,ad_name,campaign_id,campaign_name,is_organic,platform';

    /** Graph error code for an invalid or expired token. */
    private const INVALID_TOKEN = 190;

    /** Stops a runaway pager; 100 pages of 100 is far above an hour of leads. */
    private const MAX_PAGES = 100;

    public function platform(): LeadAdsPlatform
    {
        return LeadAdsPlatform::Meta;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.meta.client_id')) && filled(config('services.meta.client_secret'));
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return 'https://www.facebook.com/'.$this->version().'/dialog/oauth?'.http_build_query([
            'client_id' => config('services.meta.client_id'),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(',', self::SCOPES),
            'response_type' => 'code',
        ]);
    }

    public function connect(string $code, string $redirectUri): array
    {
        $shortLived = $this->get('oauth/access_token', [
            'client_id' => config('services.meta.client_id'),
            'client_secret' => config('services.meta.client_secret'),
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        $longLived = $this->get('oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('services.meta.client_id'),
            'client_secret' => config('services.meta.client_secret'),
            'fb_exchange_token' => $shortLived['access_token'] ?? '',
        ]);

        $accounts = [];

        foreach ($this->paginate('me/accounts', [
            'access_token' => $longLived['access_token'] ?? '',
            'fields' => 'id,name,access_token',
            'limit' => 100,
        ]) as $page) {
            $accounts[] = new DiscoveredAccount(
                accountId: (string) $page['id'],
                name: (string) ($page['name'] ?? 'Facebook Page '.$page['id']),
                accessToken: (string) $page['access_token'],
            );
        }

        return $accounts;
    }

    public function fetchLeads(IntegrationConnection $connection, CarbonInterface $since): iterable
    {
        $token = $connection->access_token;

        $forms = $this->paginate("{$connection->account_id}/leadgen_forms", [
            'access_token' => $token,
            'fields' => 'id,name,status',
            'limit' => 100,
        ]);

        foreach ($forms as $form) {
            $leads = $this->paginate("{$form['id']}/leads", [
                'access_token' => $token,
                'fields' => self::LEAD_FIELDS,
                'limit' => 100,
                'filtering' => json_encode([[
                    'field' => 'time_created',
                    'operator' => 'GREATER_THAN',
                    'value' => $since->getTimestamp(),
                ]]),
            ]);

            foreach ($leads as $lead) {
                yield $this->toProviderLead($lead, $form);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $lead
     * @param  array<string, mixed>  $form
     */
    private function toProviderLead(array $lead, array $form): ProviderLead
    {
        $fields = [];

        // [{ name: "email", values: ["a@b.example"] }, …]
        foreach ($lead['field_data'] ?? [] as $answer) {
            $values = array_filter(
                array_map('strval', (array) ($answer['values'] ?? [])),
                static fn (string $value): bool => $value !== '',
            );

            if (isset($answer['name']) && $values !== []) {
                $fields[(string) $answer['name']] = implode(', ', $values);
            }
        }

        $isInstagram = ($lead['platform'] ?? null) === 'ig';
        $isOrganic = (bool) ($lead['is_organic'] ?? false);

        return new ProviderLead(
            id: (string) $lead['id'],
            submittedAt: Carbon::parse((string) ($lead['created_time'] ?? 'now')),
            fields: $fields,
            attribution: array_filter([
                'utm_source' => $isInstagram ? 'instagram' : 'facebook',
                'utm_medium' => $isOrganic ? 'organic_social' : 'paid_social',
                'utm_campaign' => $lead['campaign_name'] ?? null,
                'utm_content' => $lead['ad_name'] ?? null,
            ]),
            metadata: array_filter([
                'lead_ads_platform' => $isInstagram ? 'Instagram' : 'Facebook',
                'lead_ads_form' => $form['name'] ?? null,
                'lead_ads_form_id' => $form['id'] ?? null,
                'lead_ads_ad_id' => $lead['ad_id'] ?? null,
                'lead_ads_campaign_id' => $lead['campaign_id'] ?? null,
            ]),
        );
    }

    /**
     * Walks a Graph edge, following `paging.next` until it runs out.
     *
     * @param  array<string, mixed>  $query
     * @return iterable<array<string, mixed>>
     */
    private function paginate(string $path, array $query): iterable
    {
        $body = $this->get($path, $query);

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            foreach ($body['data'] ?? [] as $item) {
                yield $item;
            }

            $next = $body['paging']['next'] ?? null;

            if (! is_string($next) || ($body['data'] ?? []) === []) {
                return;
            }

            // The next URL already carries the token and every parameter.
            $body = $this->decode($this->send(fn () => Http::timeout(30)->get($next)));
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $path, array $query): array
    {
        $url = 'https://graph.facebook.com/'.$this->version().'/'.ltrim($path, '/');

        return $this->decode($this->send(fn () => Http::timeout(30)->get($url, $query)));
    }

    /**
     * @param  callable(): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $e) {
            throw new ProviderRequestException('Could not reach Facebook: '.$e->getMessage(), previous: $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(Response $response): array
    {
        if ($response->successful()) {
            return (array) $response->json();
        }

        $error = (array) $response->json('error', []);
        $message = (string) ($error['message'] ?? 'Facebook returned HTTP '.$response->status());

        if (($error['code'] ?? null) === self::INVALID_TOKEN || $response->status() === 401) {
            throw new ProviderAuthException('Facebook no longer accepts this connection: '.$message);
        }

        // 10 and 200–299 are permission errors: also something only the user fixes.
        $code = (int) ($error['code'] ?? 0);

        if ($code === 10 || ($code >= 200 && $code < 300)) {
            throw new ProviderAuthException('Facebook refused permission: '.$message);
        }

        throw new ProviderRequestException('Facebook request failed: '.$message);
    }

    private function version(): string
    {
        return (string) config('services.meta.api_version');
    }
}
