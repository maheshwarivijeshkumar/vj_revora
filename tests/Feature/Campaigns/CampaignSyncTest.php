<?php

declare(strict_types=1);

use App\Domain\Campaigns\Actions\SyncCampaigns;
use App\Domain\Tenancy\TenantContext;
use App\Models\AdCampaign;
use App\Models\IntegrationConnection;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Campaign sync
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
});

it('reads Facebook and Instagram campaigns from a connected page', function (): void {
    IntegrationConnection::factory()->create([
        'tenant_id' => $this->tenant->id,
        'account_id' => '111',
        'account_name' => 'Berg Media',
    ]);

    Http::fake([
        'graph.facebook.com/*/111/assigned_ad_accounts*' => Http::response([
            'data' => [['id' => 'act_9', 'name' => 'Berg']],
        ]),
        'graph.facebook.com/*/act_9/campaigns*' => Http::response([
            'data' => [[
                'id' => '55',
                'name' => 'Spring leads',
                'effective_status' => 'ACTIVE',
                'objective' => 'OUTCOME_LEADS',
                'start_time' => '2026-10-01T00:00:00+0000',
            ]],
        ]),
    ]);
    Http::preventStrayRequests();

    expect(app(SyncCampaigns::class)->handle())->toBe(1);

    $campaign = AdCampaign::query()->first();

    expect($campaign->platform)->toBe('meta')
        ->and($campaign->name)->toBe('Spring leads')
        ->and($campaign->status)->toBe('ACTIVE')
        ->and($campaign->external_id)->toBe('55');
});

it('reads LinkedIn campaigns from a connected ad account', function (): void {
    IntegrationConnection::factory()->linkedIn()->create([
        'tenant_id' => $this->tenant->id,
        'account_id' => 'urn:li:sponsoredAccount:42',
    ]);

    Http::fake([
        'api.linkedin.com/rest/adAccounts/42/adCampaigns*' => Http::response([
            'elements' => [[
                'id' => 777,
                'name' => 'Hiring managers',
                'status' => 'ACTIVE',
                'objectiveType' => 'LEAD_GENERATION',
            ]],
            'paging' => ['total' => 1],
        ]),
    ]);
    Http::preventStrayRequests();

    app(SyncCampaigns::class)->handle();

    expect(AdCampaign::query()->first())
        ->platform->toBe('linkedin')
        ->name->toBe('Hiring managers')
        ->external_id->toBe('777');
});

it('keeps Google, TikTok and any other network named on a lead', function (): void {
    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'utm' => ['utm_source' => 'google', 'utm_campaign' => 'Brand search'],
        'metadata' => ['lead_ads_campaign_id' => 'g-1'],
    ]);
    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'utm' => ['utm_source' => 'tiktok', 'utm_campaign' => 'Shorts'],
        'metadata' => ['lead_ads_campaign_id' => 'tt-1'],
    ]);
    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'utm' => ['utm_source' => 'Snapchat', 'utm_campaign' => 'Stories'],
    ]);

    Http::preventStrayRequests();

    app(SyncCampaigns::class)->handle();

    expect(AdCampaign::query()->orderBy('platform')->pluck('name', 'platform')->all())->toBe([
        'google' => 'Brand search',
        'snapchat' => 'Stories',
        'tiktok' => 'Shorts',
    ]);
});

it('does not duplicate a Meta campaign the lead also names', function (): void {
    IntegrationConnection::factory()->create([
        'tenant_id' => $this->tenant->id,
        'account_id' => '111',
    ]);

    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'utm' => ['utm_source' => 'instagram', 'utm_campaign' => 'Spring leads'],
        'metadata' => [
            'lead_ads_platform' => 'Instagram',
            'lead_ads_campaign_id' => '55',
        ],
    ]);

    Http::fake([
        'graph.facebook.com/*/111/assigned_ad_accounts*' => Http::response([
            'data' => [['id' => 'act_9', 'name' => 'Berg']],
        ]),
        'graph.facebook.com/*/act_9/campaigns*' => Http::response([
            'data' => [[
                'id' => '55',
                'name' => 'Spring leads',
                'effective_status' => 'ACTIVE',
            ]],
        ]),
    ]);
    Http::preventStrayRequests();

    app(SyncCampaigns::class)->handle();

    expect(AdCampaign::query()->count())->toBe(1)
        ->and(AdCampaign::query()->value('platform'))->toBe('meta');
});

it('still records lead campaigns when the ad account cannot be read', function (): void {
    IntegrationConnection::factory()->create([
        'tenant_id' => $this->tenant->id,
        'account_id' => '111',
    ]);

    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'utm' => ['utm_source' => 'google', 'utm_campaign' => 'Brand search'],
        'metadata' => ['lead_ads_campaign_id' => 'g-1'],
    ]);

    Http::fake([
        'graph.facebook.com/*' => Http::response(['error' => ['message' => 'no', 'code' => 100]], 400),
    ]);
    Http::preventStrayRequests();

    app(SyncCampaigns::class)->handle();

    expect(AdCampaign::query()->pluck('platform')->all())->toBe(['google']);
});

it('skips an account that needs reconnecting', function (): void {
    IntegrationConnection::factory()->needsReconnect()->create([
        'tenant_id' => $this->tenant->id,
        'account_id' => '111',
    ]);

    Http::preventStrayRequests();

    expect(app(SyncCampaigns::class)->handle())->toBe(0);
});

it('lists campaigns for someone who can view them', function (): void {
    grantCampaignPermissions(['campaign.view']);

    AdCampaign::factory()->create([
        'tenant_id' => $this->tenant->id,
        'platform' => 'google',
        'name' => 'Brand search',
        'external_id' => 'g-1',
    ]);
    AdCampaign::factory()->create([
        'tenant_id' => $this->tenant->id,
        'platform' => 'tiktok',
        'name' => 'Shorts',
        'external_id' => 'tt-1',
    ]);

    $names = campaignNames();

    expect($names)->toBe(['Brand search', 'Shorts']);

    $filtered = campaignNames(['platform' => 'tiktok']);

    expect($filtered)->toBe(['Shorts']);
});

it('refuses the campaigns page without permission', function (): void {
    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($stranger)->get('/campaigns')->assertForbidden();
});

it('syncs the current workspace from the page', function (): void {
    grantCampaignPermissions(['campaign.view', 'campaign.manage']);

    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'utm' => ['utm_source' => 'google', 'utm_campaign' => 'Brand search'],
        'metadata' => ['lead_ads_campaign_id' => 'g-1'],
    ]);

    Http::preventStrayRequests();

    $this->actingAs(test()->user)
        ->post('/campaigns/sync')
        ->assertRedirect();

    expect(AdCampaign::query()->value('name'))->toBe('Brand search');
});

/**
 * @param  list<string>  $permissions
 */
function grantCampaignPermissions(array $permissions): void
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('Campaigner', 'web');
    $role->syncPermissions($permissions);

    test()->user = User::factory()->create(['tenant_id' => test()->tenant->id]);
    test()->user->assignRole($role);
}

/**
 * @param  array<string, string>  $query
 * @return list<string>
 */
function campaignNames(array $query = []): array
{
    $props = test()->actingAs(test()->user)
        ->get('/campaigns?'.http_build_query($query))
        ->assertOk()
        ->viewData('page')['props'];

    return array_column($props['campaigns'], 'name');
}
