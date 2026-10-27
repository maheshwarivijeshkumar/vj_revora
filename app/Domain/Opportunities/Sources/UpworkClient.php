<?php

declare(strict_types=1);

namespace App\Domain\Opportunities\Sources;

use App\Domain\Integrations\Exceptions\ProviderAuthException;
use App\Domain\Integrations\Exceptions\ProviderRequestException;
use App\Domain\Opportunities\DataObjects\JobPostData;
use App\Models\UpworkAccount;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Reads Upwork job postings through Upwork's official GraphQL API.
 *
 * Read-only by design. Upwork forbids unapproved bots and spammed proposals,
 * so the CRM finds and scores jobs and drafts a proposal, and a person reviews
 * and submits it on Upwork. Needs an API key Upwork has approved with the
 * "Read marketplace Job Postings" permission.
 *
 * @see https://www.upwork.com/developer/documentation/graphql/api/docs/index.html
 */
final class UpworkClient
{
    private const AUTHORIZE_URL = 'https://www.upwork.com/ab/account-security/oauth2/authorize';

    private const TOKEN_URL = 'https://www.upwork.com/api/v3/oauth2/token';

    private const GRAPHQL_URL = 'https://api.upwork.com/graphql';

    private const SEARCH = <<<'GRAPHQL'
        query search($filter: MarketplaceJobPostingsSearchFilter, $searchType: MarketplaceJobPostingSearchType) {
          marketplaceJobPostingsSearch(marketPlaceJobFilter: $filter, searchType: $searchType) {
            edges {
              node {
                id
                title
                description
                ciphertext
                createdDateTime
                publishedDateTime
                totalApplicants
                experienceLevel
                amount { rawValue currency }
                hourlyBudgetMin { rawValue currency }
                hourlyBudgetMax { rawValue currency }
                skills { prettyName }
                client {
                  totalSpent { rawValue currency }
                  totalHires
                  totalFeedback
                  verificationStatus
                  location { country }
                }
              }
            }
            pageInfo { hasNextPage endCursor }
          }
        }
        GRAPHQL;

    public function isConfigured(): bool
    {
        return filled(config('services.upwork.client_id')) && filled(config('services.upwork.client_secret'));
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.upwork.client_id'),
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    /**
     * @return array{access_token: string, refresh_token: string|null, expires_at: int}
     */
    public function exchangeCode(string $code, string $redirectUri): array
    {
        return $this->token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);
    }

    /**
     * Recent job postings matching a search expression.
     *
     * @return iterable<JobPostData>
     *
     * @throws ProviderAuthException|ProviderRequestException
     */
    public function search(UpworkAccount $account, string $expression, int $daysPosted = 3, int $limit = 50): iterable
    {
        $response = $this->graphql($account, self::SEARCH, [
            'searchType' => 'USER_JOBS_SEARCH',
            'filter' => [
                'searchExpression_eq' => $expression,
                'daysPosted_eq' => $daysPosted,
                'pagination_eq' => ['first' => $limit, 'after' => '0'],
            ],
        ]);

        foreach ((array) ($response['data']['marketplaceJobPostingsSearch']['edges'] ?? []) as $edge) {
            $job = (array) ($edge['node'] ?? []);

            if (empty($job['ciphertext']) || empty($job['title'])) {
                continue;
            }

            yield $this->toJob($job);
        }
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private function toJob(array $job): JobPostData
    {
        $fixed = isset($job['amount']['rawValue']) ? (float) $job['amount']['rawValue'] : null;
        $hourlyMin = isset($job['hourlyBudgetMin']['rawValue']) ? (float) $job['hourlyBudgetMin']['rawValue'] : null;
        $hourlyMax = isset($job['hourlyBudgetMax']['rawValue']) ? (float) $job['hourlyBudgetMax']['rawValue'] : null;
        $isHourly = ($hourlyMin ?? 0) > 0 || ($hourlyMax ?? 0) > 0;
        $client = (array) ($job['client'] ?? []);
        $cipher = (string) $job['ciphertext'];

        return new JobPostData(
            source: 'upwork',
            externalId: $cipher,
            title: (string) $job['title'],
            url: 'https://www.upwork.com/jobs/'.(str_starts_with($cipher, '~') ? $cipher : '~'.$cipher),
            company: null,
            description: $job['description'] ?? null,
            location: $client['location']['country'] ?? null,
            jobType: $isHourly ? 'hourly' : 'fixed',
            tags: array_values(array_filter(array_map(
                fn (mixed $skill): string => (string) (is_array($skill) ? ($skill['prettyName'] ?? '') : ''),
                (array) ($job['skills'] ?? []),
            ))),
            budgetType: $isHourly ? 'hourly' : ($fixed > 0 ? 'fixed' : null),
            budgetMin: $isHourly ? $hourlyMin : null,
            budgetMax: $isHourly ? $hourlyMax : ($fixed > 0 ? $fixed : null),
            currency: (string) ($job['amount']['currency'] ?? $job['hourlyBudgetMin']['currency'] ?? 'USD'),
            signals: array_filter([
                'applicants' => $job['totalApplicants'] ?? null,
                'experience_level' => $job['experienceLevel'] ?? null,
                'client_spent' => isset($client['totalSpent']['rawValue']) ? (float) $client['totalSpent']['rawValue'] : null,
                'client_hires' => $client['totalHires'] ?? null,
                'client_feedback' => $client['totalFeedback'] ?? null,
                'payment_verified' => ($client['verificationStatus'] ?? null) === 'VERIFIED',
            ], fn (mixed $v): bool => $v !== null),
            postedAt: $this->date($job['publishedDateTime'] ?? $job['createdDateTime'] ?? null),
        );
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     */
    private function graphql(UpworkAccount $account, string $query, array $variables): array
    {
        $headers = $account->organization_id === null ? [] : ['X-Upwork-API-TenantId' => $account->organization_id];

        $response = $this->send(fn (): Response => Http::withToken($this->accessToken($account))
            ->withHeaders($headers)
            ->timeout(60)
            ->post(self::GRAPHQL_URL, ['query' => $query, 'variables' => $variables]));

        if ($response->status() === 401 || $response->status() === 403) {
            throw new ProviderAuthException('Upwork refused access. Reconnect Upwork, and check the API key has "Read marketplace Job Postings".');
        }

        if (! $response->successful()) {
            throw new ProviderRequestException('Upwork returned HTTP '.$response->status());
        }

        $errors = (array) $response->json('errors', []);

        if ($errors !== [] && $response->json('data') === null) {
            throw new ProviderRequestException('Upwork search failed: '.($errors[0]['message'] ?? 'unknown error'));
        }

        return (array) $response->json();
    }

    private function accessToken(UpworkAccount $account): string
    {
        $credentials = $account->credentials;

        if (($credentials['expires_at'] ?? 0) > now()->addMinute()->getTimestamp()) {
            return (string) $credentials['access_token'];
        }

        if (empty($credentials['refresh_token'])) {
            throw new ProviderAuthException('The Upwork connection expired. Reconnect Upwork.');
        }

        $fresh = $this->token(['grant_type' => 'refresh_token', 'refresh_token' => (string) $credentials['refresh_token']]);
        $account->forceFill(['credentials' => [...$credentials, ...array_filter($fresh)]])->save();

        return $fresh['access_token'];
    }

    /**
     * @param  array<string, string>  $form
     * @return array{access_token: string, refresh_token: string|null, expires_at: int}
     */
    private function token(array $form): array
    {
        $response = $this->send(fn (): Response => Http::asForm()->acceptJson()->timeout(30)->post(self::TOKEN_URL, [
            ...$form,
            'client_id' => config('services.upwork.client_id'),
            'client_secret' => config('services.upwork.client_secret'),
        ]));

        if (! $response->successful() || blank($response->json('access_token'))) {
            throw new ProviderAuthException('Upwork sign-in was refused: '.($response->json('error_description') ?? $response->json('error') ?? 'HTTP '.$response->status()));
        }

        return [
            'access_token' => (string) $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token'),
            'expires_at' => now()->addSeconds((int) ($response->json('expires_in') ?? 86400))->getTimestamp(),
        ];
    }

    /**
     * @param  callable(): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $e) {
            throw new ProviderRequestException('Could not reach Upwork: '.$e->getMessage(), previous: $e);
        }
    }

    private function date(mixed $value): ?Carbon
    {
        try {
            return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
