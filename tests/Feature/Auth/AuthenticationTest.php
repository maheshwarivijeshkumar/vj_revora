<?php

declare(strict_types=1);

use App\Domain\Billing\Entitlements;
use App\Domain\Tenancy\TenantContext;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Authentication and tenant-aware authorization
|--------------------------------------------------------------------------
|
| These cover two bugs that the fail-closed tenant design caused, both found by
| driving the running application rather than by inspection. They are regression
| tests: each one failed before its fix.
|
|   1. Sign-in was impossible. With no tenant bound at login, TenantScope failed
|      closed and the credential lookup returned nothing — for every user.
|   2. Every permission check returned false. Roles are tenant-scoped through
|      Spatie's team mode, and nothing was setting the team id per request.
|
*/

function makeTenantWithOwner(string $email = 'owner@example.test'): array
{
    $tenant = Tenant::factory()->create();

    // Key comes from the factory's unique sequence: this helper is called
    // twice in the ambiguous-address test.
    $plan = Plan::factory()->create();
    $plan->features()->createMany([
        ['feature_key' => 'automation', 'value' => true, 'is_unlimited' => false],
        ['feature_key' => 'white_label', 'value' => false, 'is_unlimited' => false],
        ['feature_key' => 'leads_per_month', 'value' => 500, 'is_unlimited' => false],
        ['feature_key' => 'api_requests', 'value' => true, 'is_unlimited' => true],
    ]);

    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    Permission::findOrCreate('lead.view', 'web');
    $role = Role::findOrCreate('Owner', 'web');
    $role->syncPermissions(['lead.view']);

    $user = app(TenantContext::class)->runAs($tenant, fn (): User => User::create([
        'name' => 'Owner',
        'email' => $email,
        'password' => 'correct-horse-battery',
    ]));

    $user->assignRole($role);

    // Reload so every column is present. A freshly created model carries only
    // the attributes that were inserted, and strict mode rejects reads of the
    // rest — whereas a real request always loads the full row.
    return [$tenant, $user->fresh()];
}

// --- Regression 1: sign-in must work despite fail-closed scoping ------------

it('authenticates a user even though no tenant is bound at sign-in', function (): void {
    [, $user] = makeTenantWithOwner();

    app(TenantContext::class)->forget();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'correct-horse-battery',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password', function (): void {
    [, $user] = makeTenantWithOwner();

    app(TenantContext::class)->forget();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'not-the-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('validates on the server, not in the browser', function (): void {
    $this->post('/login', [])->assertSessionHasErrors(['email', 'password']);
});

it('refuses an ambiguous address shared by two workspaces', function (): void {
    // users.email is unique per tenant, so the same address can exist twice.
    // With no tenant identified by the request, guessing a workspace would be
    // a cross-tenant login — refusing is the only safe answer (ADR-010).
    makeTenantWithOwner('shared@example.test');
    makeTenantWithOwner('shared@example.test');

    app(TenantContext::class)->forget();

    $this->post('/login', [
        'email' => 'shared@example.test',
        'password' => 'correct-horse-battery',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

// --- Regression 2: tenant-scoped roles must actually resolve ----------------

it('resolves tenant-scoped permissions for a signed-in user', function (): void {
    [, $user] = makeTenantWithOwner();

    app(TenantContext::class)->forget();
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);

    $this->actingAs($user)->get('/dashboard')->assertOk();

    // ResolveTenant binds both the tenant and Spatie's team id. Without the
    // latter this returns an empty collection and the whole UI renders as if
    // the user were unauthorized.
    expect($user->fresh()->getAllPermissions()->pluck('name'))
        ->toContain('lead.view');
});

it('shares permissions and entitlements with the front end', function (): void {
    [, $user] = makeTenantWithOwner();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('tenant.status', 'active')
            ->where('auth.user.email', $user->email)
            ->where('entitlements.automation.enabled', true)
            ->where('entitlements.white_label.enabled', false)
            ->where('entitlements.leads_per_month.limit', 500)
            ->where('entitlements.api_requests.limit', null)   // unlimited
        );
});

// --- Entitlements -----------------------------------------------------------

it('grants nothing when the subscription is not usable', function (): void {
    [$tenant] = makeTenantWithOwner();

    $tenant->subscription->update(['status' => 'expired']);
    Entitlements::flush($tenant);

    $entitlements = app(Entitlements::class);

    expect($entitlements->hasFeature('automation', $tenant))->toBeFalse()
        ->and($entitlements->snapshot($tenant))->toBeEmpty();
});

it('treats an unlimited grant as no cap rather than no access', function (): void {
    [$tenant] = makeTenantWithOwner();

    $entitlements = app(Entitlements::class);

    expect($entitlements->hasFeature('api_requests', $tenant))->toBeTrue()
        ->and($entitlements->limit('api_requests', $tenant))->toBeNull()
        ->and($entitlements->withinLimit('api_requests', 1_000_000, $tenant))->toBeTrue();
});

it('blocks work that would exceed a plan limit', function (): void {
    [$tenant] = makeTenantWithOwner();

    $entitlements = app(Entitlements::class);

    expect($entitlements->withinLimit('leads_per_month', 500, $tenant))->toBeTrue()
        ->and($entitlements->withinLimit('leads_per_month', 501, $tenant))->toBeFalse()
        ->and($entitlements->hasFeature('white_label', $tenant))->toBeFalse();
});

it('signs a user out', function (): void {
    [, $user] = makeTenantWithOwner();

    $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

    $this->assertGuest();
});
