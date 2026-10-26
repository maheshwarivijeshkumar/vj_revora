<?php

declare(strict_types=1);

use App\Domain\Integrations\Actions\SyncLeadAdsConnection;
use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Leads\Services\LeadScorer;
use App\Domain\Tenancy\TenantContext;
use App\Jobs\SyncLeadAdsConnectionJob;
use App\Models\IntegrationConnection;
use App\Models\IntegrationSyncRun;
use App\Models\Lead;
use App\Models\LeadScoreRule;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Lead ads sync
|--------------------------------------------------------------------------
|
| Facebook / Instagram and LinkedIn leads polled into the capture pipeline
| (§12, §13, §57). Replays must never duplicate (§58).
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);

    foreach (LeadScorer::defaultRules() as $i => $rule) {
        LeadScoreRule::create([...$rule, 'sort_order' => $i]);
    }

    config([
        'services.meta.api_version' => 'v26.0',
        'services.linkedin.api_version' => '202609',
        'verification.queue_recheck' => false,
    ]);

    Http::preventStrayRequests();
});

function fakeMetaPage(string $pageId, array $leads): void
{
    Http::fake([
        "graph.facebook.com/v26.0/{$pageId}/leadgen_forms*" => Http::response([
            'data' => [['id' => 'form-1', 'name' => 'Free consultation', 'status' => 'ACTIVE']],
        ]),
        'graph.facebook.com/v26.0/form-1/leads*' => Http::response(['data' => $leads]),
    ]);
}

function metaLead(string $id, array $overrides = []): array
{
    return [
        'id' => $id,
        'created_time' => '2026-10-26T08:00:00+0000',
        'form_id' => 'form-1',
        'ad_name' => 'Tax season video',
        'campaign_name' => 'Q4 tax returns',
        'platform' => 'fb',
        'field_data' => [
            ['name' => 'full_name', 'values' => ['Amara Okafor']],
            ['name' => 'email', 'values' => ["{$id}@example.com"]],
            ['name' => 'phone_number', 'values' => ['+971501234567']],
        ],
        ...$overrides,
    ];
}

// --- Facebook & Instagram ---------------------------------------------------

it('imports Facebook lead ads into the lead pipeline with their attribution', function (): void {
    $connection = IntegrationConnection::factory()->create(['tenant_id' => $this->tenant->id]);
    fakeMetaPage($connection->account_id, [metaLead('111')]);

    $run = app(SyncLeadAdsConnection::class)->handle($connection, IntegrationSyncRun::TRIGGER_SCHEDULE);

    $lead = Lead::query()->sole();

    expect($run->status)->toBe(IntegrationSyncRun::STATUS_SUCCEEDED)
        ->and($run->leads_created)->toBe(1)
        ->and($lead->full_name)->toBe('Amara Okafor')
        ->and($lead->email)->toBe('111@example.com')
        ->and($lead->lead_source_id)->toBe($connection->lead_source_id)
        ->and($lead->utm)->toMatchArray([
            'utm_source' => 'facebook',
            'utm_medium' => 'paid_social',
            'utm_campaign' => 'Q4 tax returns',
        ])
        ->and($lead->metadata['lead_ads_form'])->toBe('Free consultation')
        ->and($connection->fresh()->last_synced_at)->not->toBeNull();
});

it('labels leads from Instagram placements as Instagram', function (): void {
    $connection = IntegrationConnection::factory()->create(['tenant_id' => $this->tenant->id]);
    fakeMetaPage($connection->account_id, [metaLead('222', ['platform' => 'ig'])]);

    app(SyncLeadAdsConnection::class)->handle($connection, IntegrationSyncRun::TRIGGER_SCHEDULE);

    $lead = Lead::query()->sole();

    expect($lead->utm['utm_source'])->toBe('instagram')
        ->and($lead->metadata['lead_ads_platform'])->toBe('Instagram');
});

it('skips leads it has already imported when the sync window overlaps', function (): void {
    $connection = IntegrationConnection::factory()->create(['tenant_id' => $this->tenant->id]);
    fakeMetaPage($connection->account_id, [metaLead('333')]);

    $sync = app(SyncLeadAdsConnection::class);
    $sync->handle($connection, IntegrationSyncRun::TRIGGER_SCHEDULE);
    $second = $sync->handle($connection->fresh(), IntegrationSyncRun::TRIGGER_SCHEDULE);

    expect(Lead::query()->count())->toBe(1)
        ->and($second->leads_created)->toBe(0)
        ->and($second->leads_skipped)->toBe(1);
});

it('asks for a reconnect when Facebook rejects the token', function (): void {
    $connection = IntegrationConnection::factory()->create(['tenant_id' => $this->tenant->id]);

    Http::fake([
        "graph.facebook.com/v26.0/{$connection->account_id}/leadgen_forms*" => Http::response([
            'error' => ['message' => 'Error validating access token', 'code' => 190],
        ], 400),
    ]);

    $run = app(SyncLeadAdsConnection::class)->handle($connection, IntegrationSyncRun::TRIGGER_SCHEDULE);
    $connection->refresh();

    expect($run->status)->toBe(IntegrationSyncRun::STATUS_FAILED)
        ->and($connection->status)->toBe(ConnectionStatus::NeedsReconnect)
        ->and($connection->last_error)->toContain('Error validating access token')
        ->and($connection->last_synced_at)->toBeNull();
});

// --- LinkedIn ---------------------------------------------------------------

it('names LinkedIn answers from the form definition and skips test leads', function (): void {
    $connection = IntegrationConnection::factory()->linkedIn()->create(['tenant_id' => $this->tenant->id]);

    $response = fn (string $id, bool $test): array => [
        'id' => $id,
        'submittedAt' => 1793000000000,
        'testLead' => $test,
        'versionedLeadGenFormUrn' => 'urn:li:versionedLeadGenForm:(urn:li:leadGenForm:3162,1)',
        'leadMetadataInfo' => ['sponsoredLeadMetadataInfo' => ['campaign' => [
            'id' => 'urn:li:sponsoredCampaign:1', 'name' => 'CFO outreach',
        ]]],
        'formResponse' => ['answers' => [
            ['questionId' => 1, 'answerDetails' => ['textQuestionAnswer' => ['answer' => 'Priya']]],
            ['questionId' => 2, 'answerDetails' => ['textQuestionAnswer' => ['answer' => 'Nair']]],
            ['questionId' => 3, 'answerDetails' => ['textQuestionAnswer' => ['answer' => "{$id}@example.com"]]],
            ['questionId' => 4, 'answerDetails' => ['textQuestionAnswer' => ['answer' => 'Nair & Co']]],
        ]],
    ];

    Http::fake([
        'api.linkedin.com/rest/leadFormResponses*' => Http::response([
            'elements' => [$response('real-lead', false), $response('test-lead', true)],
        ]),
        'api.linkedin.com/rest/leadForms/3162' => Http::response(['content' => ['questions' => [
            ['questionId' => 1, 'predefinedField' => 'FIRST_NAME'],
            ['questionId' => 2, 'predefinedField' => 'LAST_NAME'],
            ['questionId' => 3, 'predefinedField' => 'EMAIL'],
            ['questionId' => 4, 'predefinedField' => 'COMPANY_NAME'],
        ]]]),
    ]);

    app(SyncLeadAdsConnection::class)->handle($connection, IntegrationSyncRun::TRIGGER_SCHEDULE);

    $lead = Lead::query()->sole();

    expect($lead->first_name)->toBe('Priya')
        ->and($lead->last_name)->toBe('Nair')
        ->and($lead->email)->toBe('real-lead@example.com')
        ->and($lead->company_name)->toBe('Nair & Co')
        ->and($lead->utm['utm_source'])->toBe('linkedin')
        ->and($lead->utm['utm_campaign'])->toBe('CFO outreach');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'leadFormResponses')
        && $request->hasHeader('LinkedIn-Version', '202609')
        && $request->hasHeader('X-Restli-Protocol-Version', '2.0.0'));
});

it('asks for a reconnect once a LinkedIn token has expired, without calling LinkedIn', function (): void {
    $connection = IntegrationConnection::factory()->linkedIn()->create([
        'tenant_id' => $this->tenant->id,
        'token_expires_at' => now()->subDay(),
    ]);

    app(SyncLeadAdsConnection::class)->handle($connection, IntegrationSyncRun::TRIGGER_SCHEDULE);

    expect($connection->fresh()->status)->toBe(ConnectionStatus::NeedsReconnect);
    Http::assertNothingSent();
});

// --- Scheduling -------------------------------------------------------------

it('queues an hourly sync for each working connection of active workspaces', function (): void {
    $working = IntegrationConnection::factory()->create(['tenant_id' => $this->tenant->id]);
    IntegrationConnection::factory()->needsReconnect()->create(['tenant_id' => $this->tenant->id]);

    $suspended = Tenant::factory()->suspended()->create();
    app(TenantContext::class)->runAs($suspended, fn () => IntegrationConnection::factory()->create([
        'tenant_id' => $suspended->id,
    ]));

    Queue::fake([SyncLeadAdsConnectionJob::class]);

    $this->artisan('leads:sync-ads')->assertSuccessful();

    Queue::assertPushed(SyncLeadAdsConnectionJob::class, 1);
    Queue::assertPushed(
        SyncLeadAdsConnectionJob::class,
        fn (SyncLeadAdsConnectionJob $job): bool => $job->uniqueId() === (string) $working->id
            && $job->tenantId === $this->tenant->id,
    );
});
