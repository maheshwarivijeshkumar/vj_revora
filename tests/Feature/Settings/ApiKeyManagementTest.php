<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| API key management
|--------------------------------------------------------------------------
|
| §48. The secret is shown once and never again; a key is retired by revoking
| it, not by deleting the row, so the request log stays readable (§50, §54).
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

    $this->seed(PermissionSeeder::class);

    $role = Role::findOrCreate('Developer', 'web');
    $role->syncPermissions(['api.view', 'api.create', 'api.revoke']);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);
});

// --- Access -----------------------------------------------------------------

it('gates each action behind its own permission', function (): void {
    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $key = ApiKey::mint('Existing', ['leads.read'])['key'];

    $this->actingAs($stranger)->get('/settings/api-keys')->assertForbidden();

    $this->actingAs($stranger)
        ->post('/settings/api-keys', ['name' => 'Nope', 'scopes' => ['leads.read']])
        ->assertForbidden();

    $this->actingAs($stranger)->delete("/settings/api-keys/{$key->id}")->assertForbidden();

    expect($key->refresh()->revoked_at)->toBeNull();
});

// --- Listing ----------------------------------------------------------------

it('lists keys without ever exposing the hash', function (): void {
    ApiKey::mint('Zapier', ['leads.write']);

    $props = $this->actingAs($this->user)
        ->get('/settings/api-keys')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['keys'])->toHaveCount(1)
        ->and($props['keys'][0]['name'])->toBe('Zapier')
        ->and($props['keys'][0]['is_usable'])->toBeTrue()
        // Only the prefix. It identifies a key in a log without being usable.
        ->and($props['keys'][0])->not->toHaveKey('hash')
        ->and($props['keys'][0])->not->toHaveKey('token');
});

it('shows only the current workspace keys', function (): void {
    ApiKey::mint('Ours', ['leads.read']);

    $other = Tenant::factory()->create();
    app(TenantContext::class)->runAs($other, fn () => ApiKey::mint('Theirs', ['leads.read']));

    $props = $this->actingAs($this->user)
        ->get('/settings/api-keys')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['keys'])->toHaveCount(1)
        ->and($props['keys'][0]['name'])->toBe('Ours');
});

// --- Creating ---------------------------------------------------------------

it('flashes the secret exactly once and stores only its hash', function (): void {
    $this->actingAs($this->user)
        ->from('/settings/api-keys')
        ->post('/settings/api-keys', ['name' => 'CI', 'scopes' => ['leads.write']])
        ->assertRedirect('/settings/api-keys')
        ->assertSessionHas('newApiKey');

    $key = ApiKey::sole();
    $token = session('newApiKey')['token'];

    expect($token)->toStartWith("rvk_{$key->prefix}_")
        ->and($key->hash)->not->toContain($token)
        ->and($key->created_by)->toBe($this->user->id);

    // The one render that is allowed to show it.
    $first = $this->actingAs($this->user)
        ->get('/settings/api-keys')
        ->assertOk()
        ->viewData('page')['props'];

    expect($first['flash']['newApiKey']['token'])->toBe($token);

    // And it is gone on the next, because flash data ages out after one
    // request. A reload must not be a second chance to read the secret.
    $second = $this->actingAs($this->user)
        ->get('/settings/api-keys')
        ->assertOk()
        ->viewData('page')['props'];

    expect($second['flash']['newApiKey'])->toBeNull();
});

it('mints a key that immediately authenticates against the api', function (): void {
    $this->actingAs($this->user)
        ->post('/settings/api-keys', ['name' => 'Live', 'scopes' => ['leads.read']]);

    // The round trip that matters: a key created in the UI has to work.
    $this->withHeader('Authorization', 'Bearer '.session('newApiKey')['token'])
        ->getJson('/api/v1/leads')
        ->assertOk();
});

it('requires a name and at least one scope', function (): void {
    $this->actingAs($this->user)
        ->from('/settings/api-keys')
        ->post('/settings/api-keys', ['name' => '', 'scopes' => []])
        ->assertSessionHasErrors(['name', 'scopes']);

    expect(ApiKey::count())->toBe(0);
});

it('rejects a scope that does not exist', function (): void {
    $this->actingAs($this->user)
        ->from('/settings/api-keys')
        ->post('/settings/api-keys', ['name' => 'Bad', 'scopes' => ['leads.everything']])
        ->assertSessionHasErrors('scopes.0');

    expect(ApiKey::count())->toBe(0);
});

it('does not offer a scope with no endpoint behind it', function (): void {
    $props = $this->actingAs($this->user)
        ->get('/settings/api-keys')
        ->assertOk()
        ->viewData('page')['props'];

    $offered = array_column($props['scopes'], 'value');

    // Webhooks arrive in 1.15, messaging and appointments in Phase 2. Offering
    // them now would promise access that does not exist (§124).
    expect($offered)->toContain('contacts.write')
        ->and($offered)->not->toContain('webhooks.manage')
        ->and($offered)->not->toContain('messages.send')
        ->and($offered)->not->toContain('appointments.write');
});

it('refuses to grant a scope that is not available yet', function (): void {
    $this->actingAs($this->user)
        ->from('/settings/api-keys')
        ->post('/settings/api-keys', ['name' => 'Early', 'scopes' => ['webhooks.manage']])
        ->assertSessionHasErrors('scopes.0');

    expect(ApiKey::count())->toBe(0);
});

it('refuses an expiry date that has already passed', function (): void {
    $this->actingAs($this->user)
        ->from('/settings/api-keys')
        ->post('/settings/api-keys', [
            'name' => 'Stale',
            'scopes' => ['leads.read'],
            'expires_at' => now()->subDay()->toDateString(),
        ])
        ->assertSessionHasErrors('expires_at');
});

it('stores the expiry date when it is in the future', function (): void {
    $this->actingAs($this->user)->post('/settings/api-keys', [
        'name' => 'Seasonal',
        'scopes' => ['leads.read'],
        'expires_at' => '2027-01-31',
    ]);

    expect(ApiKey::sole()->expires_at->toDateString())->toBe('2027-01-31');
});

// --- Revoking ---------------------------------------------------------------

it('revokes a key instead of deleting it', function (): void {
    $key = ApiKey::mint('Retired', ['leads.read'])['key'];

    $this->actingAs($this->user)
        ->from('/settings/api-keys')
        ->delete("/settings/api-keys/{$key->id}")
        ->assertRedirect('/settings/api-keys')
        ->assertSessionHas('success');

    // The row survives so "who was calling us last Tuesday" stays answerable.
    expect(ApiKey::count())->toBe(1)
        ->and($key->refresh()->revoked_at)->not->toBeNull()
        ->and($key->isUsable())->toBeFalse();
});

it('stops a revoked key authenticating', function (): void {
    $minted = ApiKey::mint('Leaked', ['leads.read']);

    $this->actingAs($this->user)->delete("/settings/api-keys/{$minted['key']->id}");

    // 403 rather than 401: the credential was recognised, it is simply no
    // longer permitted, and the message says which of the two happened.
    $response = $this->withHeader('Authorization', 'Bearer '.$minted['token'])
        ->getJson('/api/v1/leads')
        ->assertForbidden();

    expect($response->json('message'))->toContain('revoked');
});

it('cannot revoke a key belonging to another workspace', function (): void {
    $other = Tenant::factory()->create();

    $foreign = app(TenantContext::class)->runAs(
        $other,
        fn () => ApiKey::mint('Theirs', ['leads.read'])['key'],
    );

    $this->actingAs($this->user)
        ->delete("/settings/api-keys/{$foreign->id}")
        ->assertNotFound();

    expect($foreign->refresh()->revoked_at)->toBeNull();
});
