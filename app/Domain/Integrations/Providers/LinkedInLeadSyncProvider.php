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
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * LinkedIn Lead Sync for sponsored Lead Gen Forms (§13).
 *
 * One connection per sponsored ad account. Access needs LinkedIn's Lead Sync
 * program approval (`r_marketing_leadgen_automation`); LinkedIn issues 60-day
 * tokens with no refresh for ordinary apps, so an expired connection asks the
 * user to reconnect rather than failing quietly.
 *
 * Responses carry only question ids, so each form's definition is read once
 * per sync to name the answers.
 *
 * @see https://learn.microsoft.com/en-us/linkedin/marketing/lead-sync/leadsync
 */
final class LinkedInLeadSyncProvider implements LeadAdsProvider
{
    /** r_ads lists the ad accounts; the Lead Sync scope reads the responses. */
    private const SCOPES = ['r_ads', 'r_marketing_leadgen_automation'];

    private const PAGE_SIZE = 100;

    private const MAX_PAGES = 100;

    /**
     * LinkedIn's predefined questions, named the way LeadNormalizer expects.
     *
     * @var array<string, string>
     */
    private const PREDEFINED = [
        'FIRST_NAME' => 'first_name',
        'LAST_NAME' => 'last_name',
        'EMAIL' => 'email',
        'WORK_EMAIL' => 'work_email',
        'PHONE_NUMBER' => 'phone',
        'WORK_PHONE_NUMBER' => 'work_phone',
        'COMPANY_NAME' => 'company_name',
        'JOB_TITLE' => 'job_title',
        'COUNTRY' => 'country',
    ];

    public function platform(): LeadAdsPlatform
    {
        return LeadAdsPlatform::LinkedIn;
    }

    public function isConfigured(): bool
    {
        return filled(config('services.linkedin.client_id')) && filled(config('services.linkedin.client_secret'));
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        return 'https://www.linkedin.com/oauth/v2/authorization?'.http_build_query([
            'response_type' => 'code',
            'client_id' => config('services.linkedin.client_id'),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => implode(' ', self::SCOPES),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function connect(string $code, string $redirectUri): array
    {
        $token = $this->decode($this->send(fn (): Response => Http::asForm()
            ->timeout(30)
            ->post('https://www.linkedin.com/oauth/v2/accessToken', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => config('services.linkedin.client_id'),
                'client_secret' => config('services.linkedin.client_secret'),
                'redirect_uri' => $redirectUri,
            ])));

        $accessToken = (string) ($token['access_token'] ?? '');
        $expiresAt = isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null;

        $users = $this->decode($this->send(
            fn (): Response => $this->api($accessToken)->get('https://api.linkedin.com/rest/adAccountUsers', ['q' => 'authenticatedUser']),
        ));

        $accounts = [];

        foreach ($users['elements'] ?? [] as $user) {
            $urn = (string) ($user['account'] ?? '');

            if ($urn === '') {
                continue;
            }

            $accounts[] = new DiscoveredAccount(
                accountId: $urn,
                name: $this->accountName($accessToken, $urn),
                accessToken: $accessToken,
                tokenExpiresAt: $expiresAt,
                settings: ['role' => $user['role'] ?? null],
            );
        }

        return $accounts;
    }

    public function fetchLeads(IntegrationConnection $connection, CarbonInterface $since): iterable
    {
        if ($connection->tokenHasExpired()) {
            throw new ProviderAuthException('The LinkedIn connection expired. LinkedIn tokens last 60 days; reconnect to keep syncing.');
        }

        $token = $connection->access_token;
        $forms = [];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $body = $this->decode($this->send(fn (): Response => $this->api($token)->get(
                'https://api.linkedin.com/rest/leadFormResponses?'.$this->responsesQuery($connection->account_id, $since, $page),
            )));

            $elements = $body['elements'] ?? [];

            foreach ($elements as $element) {
                // Test leads come from Campaign Manager's preview, not a person.
                if (($element['testLead'] ?? false) === true) {
                    continue;
                }

                $formId = $this->formId((string) ($element['versionedLeadGenFormUrn'] ?? ''));
                $forms[$formId] ??= $formId === null ? [] : $this->formQuestions($token, $formId);

                yield $this->toProviderLead($element, $forms[$formId]);
            }

            if (count($elements) < self::PAGE_SIZE) {
                return;
            }
        }
    }

    /**
     * Rest.li query for the owner finder. Built by hand because Rest.li wants
     * its parentheses and colons literal and only the URN values encoded.
     */
    private function responsesQuery(string $accountUrn, CarbonInterface $since, int $page): string
    {
        $start = $since->getTimestampMs();
        $end = now()->getTimestampMs();

        return implode('&', [
            'q=owner',
            'owner=(sponsoredAccount:'.rawurlencode($accountUrn).')',
            'leadType=(leadType:SPONSORED)',
            'limitedToTestLeads=false',
            "submittedAtTimeRange=(start:{$start},end:{$end})",
            'start='.($page * self::PAGE_SIZE),
            'count='.self::PAGE_SIZE,
        ]);
    }

    /**
     * @param  array<string, mixed>  $element
     * @param  array<int|string, array{key: string, options: array<int|string, string>}>  $questions
     */
    private function toProviderLead(array $element, array $questions): ProviderLead
    {
        $fields = [];

        foreach ($element['formResponse']['answers'] ?? [] as $answer) {
            $questionId = (string) ($answer['questionId'] ?? '');
            $question = $questions[$questionId] ?? ['key' => 'question_'.$questionId, 'options' => []];
            $details = $answer['answerDetails'] ?? [];

            $value = $details['textQuestionAnswer']['answer'] ?? null;

            if ($value === null && isset($details['multipleChoiceAnswer']['options'])) {
                $value = implode(', ', array_map(
                    fn (int|string $option): string => $question['options'][(string) $option] ?? (string) $option,
                    (array) $details['multipleChoiceAnswer']['options'],
                ));
            }

            if (is_scalar($value) && (string) $value !== '') {
                $fields[$question['key']] = (string) $value;
            }
        }

        $campaign = $element['leadMetadataInfo']['sponsoredLeadMetadataInfo']['campaign'] ?? [];

        return new ProviderLead(
            id: (string) $element['id'],
            submittedAt: Carbon::createFromTimestampMs((int) ($element['submittedAt'] ?? now()->getTimestampMs())),
            fields: $fields,
            attribution: array_filter([
                'utm_source' => 'linkedin',
                'utm_medium' => 'paid_social',
                'utm_campaign' => $campaign['name'] ?? null,
            ]),
            metadata: array_filter([
                'lead_ads_platform' => 'LinkedIn',
                'lead_ads_form_id' => $element['versionedLeadGenFormUrn'] ?? null,
                'lead_ads_campaign_id' => $campaign['id'] ?? ($element['leadMetadata']['sponsoredLeadMetadata']['campaign'] ?? null),
                'lead_ads_creative_id' => $element['associatedEntity']['associatedCreative'] ?? null,
            ]),
        );
    }

    /**
     * Question id => the field name to store the answer under.
     *
     * @return array<int|string, array{key: string, options: array<int|string, string>}>
     */
    private function formQuestions(string $token, string $formId): array
    {
        $form = $this->decode($this->send(
            fn (): Response => $this->api($token)->get("https://api.linkedin.com/rest/leadForms/{$formId}"),
        ));

        $questions = [];

        foreach ($form['content']['questions'] ?? [] as $question) {
            $predefined = $question['predefinedField'] ?? null;
            $key = self::PREDEFINED[$predefined] ?? (is_string($predefined)
                ? strtolower($predefined)
                : (string) ($question['name'] ?? 'question_'.($question['questionId'] ?? '')));

            $options = [];

            foreach ($question['questionDetails']['multipleChoiceQuestionDetails']['options'] ?? [] as $option) {
                $label = $option['text']['localized'] ?? null;
                $options[(string) ($option['id'] ?? '')] = is_array($label) ? (string) reset($label) : (string) ($option['id'] ?? '');
            }

            $questions[(string) ($question['questionId'] ?? '')] = ['key' => $key, 'options' => $options];
        }

        return $questions;
    }

    private function formId(string $versionedUrn): ?string
    {
        return preg_match('/urn:li:leadGenForm:(\d+)/', $versionedUrn, $m) === 1 ? $m[1] : null;
    }

    /** The ad account's display name; the URN is a fine fallback. */
    private function accountName(string $token, string $urn): string
    {
        $id = substr($urn, strrpos($urn, ':') + 1);

        try {
            $account = $this->decode($this->send(
                fn (): Response => $this->api($token)->get("https://api.linkedin.com/rest/adAccounts/{$id}"),
            ));
        } catch (ProviderRequestException|ProviderAuthException) {
            return "LinkedIn ad account {$id}";
        }

        return (string) ($account['name'] ?? "LinkedIn ad account {$id}");
    }

    private function api(string $token): PendingRequest
    {
        return Http::withToken($token)
            ->timeout(30)
            ->withHeaders([
                'LinkedIn-Version' => (string) config('services.linkedin.api_version'),
                'X-Restli-Protocol-Version' => '2.0.0',
            ]);
    }

    /**
     * @param  callable(): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            return $request();
        } catch (ConnectionException $e) {
            throw new ProviderRequestException('Could not reach LinkedIn: '.$e->getMessage(), previous: $e);
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

        $message = (string) ($response->json('message') ?? $response->json('error_description') ?? 'LinkedIn returned HTTP '.$response->status());

        if (in_array($response->status(), [401, 403], true)) {
            throw new ProviderAuthException('LinkedIn refused access: '.$message);
        }

        throw new ProviderRequestException('LinkedIn request failed: '.$message);
    }
}
