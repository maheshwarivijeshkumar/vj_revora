<?php

declare(strict_types=1);

use App\Domain\Integrations\Enums\ConnectionStatus;
use App\Domain\Integrations\Enums\LeadAdsPlatform;
use App\Domain\Tenancy\TenantContext;
use App\Jobs\SyncLeadAdsConnectionJob;
use App\Models\IntegrationConnection;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Integrations page
|--------------------------------------------------------------------------
|
| OAuth connection, manual sync and disconnect for lead-ad accounts (§11).
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

    $permissions = ['integration.view', 'integration.connect', 'integration.sync', 'integration.disconnect'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('Admin', 'web');
    $role->syncPermissions($permissions);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);

    config([
        'services.meta.client_id' => 'meta-app',
        'services.meta.client_secret' => 'meta-secret',
        'services.meta.api_version' => 'v26.0',
    ]);

    Http::preventStrayRequests();
});

it('requires the integration.view permission', function (): void {
    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($stranger)->get('/settings/integrations')->assertForbidden();
});

it('lists connections without ever sending their tokens to the browser', function (): void {
    IntegrationConnection::factory()->create([
        'tenant_id' => $this->tenant->id,
        'account_name' => 'Acme Tax',
        'access_token' => 'secret-page-token',
    ]);

    $response = $this->actingAs($this->user)->get('/settings/integrations')->assertOk();

    expect($response->viewData('page')['props']['connections'][0]['name'])->toBe('Acme Tax')
        ->and($response->getContent())->not->toContain('secret-page-token');
});

it('sends the user to Facebook with a state it checks on the way back', function (): void {
    $response = $this->actingAs($this->user)->get('/settings/integrations/meta/connect');

    $state = session('lead_ads_oauth_state.state');

    $response->assertRedirectContains('https://www.facebook.com/v26.0/dialog/oauth');
    expect($response->headers->get('Location'))->toContain('state='.$state)
        ->and($response->headers->get('Location'))->toContain('leads_retrieval');
});

it('refuses a callback whose state does not match', function (): void {
    $this->actingAs($this->user)
        ->withSession(['lead_ads_oauth_state' => ['platform' => 'meta', 'state' => 'expected']])
        ->get('/settings/integrations/meta/callback?code=abc&state=forged')
        ->assertRedirect('/settings/integrations')
        ->assertSessionHas('error');

    expect(IntegrationConnection::query()->count())->toBe(0);
});

it('stores each shared Page with an encrypted token and starts its first sync', function (): void {
    Http::fake([
        'graph.facebook.com/v26.0/oauth/access_token*' => Http::sequence()
            ->push(['access_token' => 'short-user-token'])
            ->push(['access_token' => 'long-user-token', 'expires_in' => 5184000]),
        'graph.facebook.com/v26.0/me/accounts*' => Http::response(['data' => [
            ['id' => '555', 'name' => 'Acme Tax', 'access_token' => 'page-token-555'],
        ]]),
    ]);
    Queue::fake([SyncLeadAdsConnectionJob::class]);

    $this->actingAs($this->user)
        ->withSession(['lead_ads_oauth_state' => ['platform' => 'meta', 'state' => 'expected']])
        ->get('/settings/integrations/meta/callback?code=abc&state=expected')
        ->assertRedirect('/settings/integrations')
        ->assertSessionHas('success');

    $connection = IntegrationConnection::query()->sole();

    expect($connection->provider)->toBe(LeadAdsPlatform::Meta)
        ->and($connection->account_id)->toBe('555')
        ->and($connection->status)->toBe(ConnectionStatus::Connected)
        ->and($connection->access_token)->toBe('page-token-555')
        ->and(DB::table('integration_connections')->value('access_token'))->not->toBe('page-token-555')
        ->and($connection->source->name)->toBe('Acme Tax (Facebook & Instagram Lead Ads)');

    Queue::assertPushed(SyncLeadAdsConnectionJob::class, 1);
});

it('queues a sync when asked', function (): void {
    $connection = IntegrationConnection::factory()->create(['tenant_id' => $this->tenant->id]);
    Queue::fake([SyncLeadAdsConnectionJob::class]);

    $this->actingAs($this->user)
        ->post("/settings/integrations/connections/{$connection->uuid}/sync")
        ->assertSessionHas('success');

    Queue::assertPushed(SyncLeadAdsConnectionJob::class, 1);
});

it('disconnects an account but keeps the leads it brought in', function (): void {
    $connection = IntegrationConnection::factory()->create(['tenant_id' => $this->tenant->id]);
    Lead::factory()->create(['tenant_id' => $this->tenant->id, 'lead_source_id' => $connection->lead_source_id]);

    $this->actingAs($this->user)
        ->delete("/settings/integrations/connections/{$connection->uuid}")
        ->assertSessionHas('success');

    expect(IntegrationConnection::query()->count())->toBe(0)
        ->and(Lead::query()->count())->toBe(1);
});

it('does not let one workspace sync or disconnect another workspace\'s account', function (): void {
    $other = Tenant::factory()->create();
    $foreign = app(TenantContext::class)->runAs($other, fn () => IntegrationConnection::factory()->create([
        'tenant_id' => $other->id,
    ]));

    $this->actingAs($this->user)->post("/settings/integrations/connections/{$foreign->uuid}/sync")->assertNotFound();
    $this->actingAs($this->user)->delete("/settings/integrations/connections/{$foreign->uuid}")->assertNotFound();
});
