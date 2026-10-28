<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Models\IntegrationApp;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

    foreach (['integration.view', 'integration.connect'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('Admin', 'web');
    $role->syncPermissions(['integration.view', 'integration.connect']);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);

    config([
        'services.meta.client_id' => null,
        'services.meta.client_secret' => null,
    ]);
});

it('saves an app id and secret from the integrations page without sending the secret back', function (): void {
    $this->actingAs($this->user)
        ->put('/settings/integrations/apps/meta', [
            'enabled' => true,
            'client_id' => 'app-123',
            'client_secret' => 'super-secret-value',
        ])
        ->assertRedirect();

    $response = $this->actingAs($this->user)->get('/settings/integrations');

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('platforms.0.configured', true)
        ->where('credentials.0.client_id', 'app-123')
        ->where('credentials.0.secret_set', true)
        ->where('credentials.0.enabled', true)
        ->where('credentials.0.provider', 'meta'));

    expect($response->getContent())->not->toContain('super-secret-value');
});

it('keeps the saved secret when a later save leaves it blank', function (): void {
    $this->actingAs($this->user)->put('/settings/integrations/apps/meta', [
        'enabled' => true,
        'client_id' => 'app-123',
        'client_secret' => 'super-secret-value',
    ]);

    $this->actingAs($this->user)->put('/settings/integrations/apps/meta', [
        'enabled' => true,
        'client_id' => 'app-456',
        'client_secret' => '',
    ])->assertRedirect();

    expect(IntegrationApp::query()->first()?->client_secret)->toBe('super-secret-value')
        ->and(IntegrationApp::query()->first()?->client_id)->toBe('app-456');
});

it('refuses the key form without permission to connect', function (): void {
    $reader = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($reader)
        ->put('/settings/integrations/apps/meta', [
            'client_id' => 'app-123',
            'client_secret' => 'super-secret-value',
        ])
        ->assertForbidden();
});

it('does not show another workspace the saved app id', function (): void {
    IntegrationApp::query()->create([
        'provider' => 'meta',
        'client_id' => 'app-123',
        'client_secret' => 'super-secret-value',
    ]);

    $other = Tenant::factory()->create();
    app(TenantContext::class)->set($other);
    app(PermissionRegistrar::class)->setPermissionsTeamId($other->id);

    $role = Role::findOrCreate('Admin', 'web');
    $role->syncPermissions(['integration.view', 'integration.connect']);
    $intruder = User::factory()->create(['tenant_id' => $other->id]);
    $intruder->assignRole($role);

    $this->actingAs($intruder)
        ->get('/settings/integrations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('credentials.0.client_id', '')
            ->where('credentials.0.secret_set', false));
});

it('refuses to enable an integration until the required keys are present', function (): void {
    $this->actingAs($this->user)
        ->from('/settings/integrations')
        ->put('/settings/integrations/apps/meta', [
            'enabled' => true,
            'client_id' => 'app-123',
            'client_secret' => '',
        ])
        ->assertRedirect('/settings/integrations')
        ->assertSessionHasErrors('client_secret');

    expect(IntegrationApp::query()->count())->toBe(0);

    $this->actingAs($this->user)
        ->from('/settings/integrations')
        ->put('/settings/integrations/apps/whatsapp', [
            'enabled' => true,
            'client_secret' => 'app-secret',
            'api_key' => '',
        ])
        ->assertRedirect('/settings/integrations')
        ->assertSessionHasErrors('api_key');
});

it('leaves a disabled integration off even when keys were saved', function (): void {
    $this->actingAs($this->user)
        ->put('/settings/integrations/apps/meta', [
            'enabled' => false,
            'client_id' => 'app-123',
            'client_secret' => 'super-secret-value',
        ])
        ->assertRedirect();

    $this->actingAs($this->user)
        ->get('/settings/integrations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('platforms.0.configured', false)
            ->where('credentials.0.enabled', false));
});

it('sends the old whatsapp settings address to the integrations page', function (): void {
    $this->actingAs($this->user)
        ->get('/settings/whatsapp')
        ->assertRedirect(route('integrations.index'));
});
