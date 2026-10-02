<?php

declare(strict_types=1);

use App\Domain\Api\ApiScope;
use App\Domain\Tenancy\Enums\TenantStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\ApiKey;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| API authentication and scopes
|--------------------------------------------------------------------------
|
| §48. The key is what identifies the workspace, so authentication and tenant
| isolation are the same mechanism here.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
});

/**
 * @param  list<string>  $scopes
 * @return array{0: ApiKey, 1: string}
 */
function mintKey(array $scopes = ['leads.read'], ?Tenant $tenant = null): array
{
    $tenant ??= test()->tenant;

    return app(TenantContext::class)->runAs($tenant, function () use ($scopes): array {
        $minted = ApiKey::mint('Test key', $scopes);

        return [$minted['key'], $minted['token']];
    });
}

function apiGet(string $token, string $path = '/api/v1/leads'): TestResponse
{
    return test()->withHeader('Authorization', "Bearer {$token}")->getJson($path);
}

// --- Token handling ---------------------------------------------------------

it('returns the secret once and never stores it', function (): void {
    [$key, $token] = mintKey();

    expect($token)->toStartWith('rvk_')
        ->and($token)->toContain($key->prefix)
        // Only a prefix and a hash are kept, so a lost key is rotated rather
        // than recovered (§48).
        ->and($key->hash)->not->toContain($token)
        ->and($key->getAttributes())->not->toHaveKey('secret');

    // And the hash is a hash, not the secret with extra steps.
    expect($key->matches('wrong-secret'))->toBeFalse()
        ->and(strlen($key->hash))->toBeGreaterThan(50);
});

it('hides the hash from serialisation', function (): void {
    [$key] = mintKey();

    // A key listing in the developer portal must not leak the hash.
    expect($key->toArray())->not->toHaveKey('hash');
});

// --- Authentication ---------------------------------------------------------

it('rejects a request with no key', function (): void {
    $this->getJson('/api/v1/leads')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer');
});

it('rejects a malformed key', function (): void {
    apiGet('not-a-key')->assertUnauthorized();
});

it('rejects a wrong secret for a real prefix', function (): void {
    [$key] = mintKey();

    apiGet("rvk_{$key->prefix}_wrongsecret")->assertUnauthorized();
});

it('gives the same answer for an unknown prefix and a wrong secret', function (): void {
    [$key] = mintKey();

    $unknown = apiGet('rvk_doesnotexist_secret');
    $wrong = apiGet("rvk_{$key->prefix}_wrongsecret");

    // Different messages would let someone confirm which prefixes exist.
    expect($unknown->json('message'))->toBe($wrong->json('message'));
});

it('accepts the key on the X-Api-Key header too', function (): void {
    [, $token] = mintKey();

    $this->withHeader('X-Api-Key', $token)
        ->getJson('/api/v1/leads')
        ->assertOk();
});

it('refuses a revoked key and says so', function (): void {
    [$key, $token] = mintKey();
    $key->forceFill(['revoked_at' => now()])->save();

    $response = apiGet($token)->assertForbidden();

    // Revoked and expired are distinct, because support needs to tell someone
    // which one happened.
    expect($response->json('message'))->toContain('revoked');
});

it('refuses an expired key and says so', function (): void {
    [$key, $token] = mintKey();
    $key->forceFill(['expires_at' => now()->subDay()])->save();

    expect(apiGet($token)->assertForbidden()->json('message'))->toContain('expired');
});

it('refuses a key belonging to a suspended workspace', function (): void {
    [, $token] = mintKey();
    $this->tenant->forceFill(['status' => TenantStatus::Suspended])->save();

    apiGet($token)->assertForbidden();
});

it('records when a key was last used', function (): void {
    [$key, $token] = mintKey();

    expect($key->last_used_at)->toBeNull();

    apiGet($token)->assertOk();

    expect($key->refresh()->last_used_at)->not->toBeNull();
});

// --- Scopes -----------------------------------------------------------------

it('refuses an endpoint the key has no scope for', function (): void {
    [, $token] = mintKey(['leads.read']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/leads', ['email' => 'a@b.example'])
        ->assertForbidden();

    // Naming the missing scope turns a dead end into something actionable.
    expect($response->json('required_scope'))->toBe('leads.write');
});

it('treats a write scope as granting its read counterpart', function (): void {
    [, $token] = mintKey(['leads.write']);

    // An integration that can create a lead can obviously read the one it
    // just made; demanding both scopes is friction with no benefit.
    apiGet($token)->assertOk();
});

it('does not treat a read scope as granting write', function (): void {
    expect(ApiScope::LeadsRead->implies(ApiScope::LeadsWrite))->toBeFalse()
        ->and(ApiScope::LeadsWrite->implies(ApiScope::LeadsRead))->toBeTrue()
        // Implication never crosses resources.
        ->and(ApiScope::LeadsWrite->implies(ApiScope::DealsRead))->toBeFalse();
});

// --- Isolation --------------------------------------------------------------

it('only ever sees the workspace that owns the key', function (): void {
    Lead::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    $other = Tenant::factory()->create();
    Lead::factory()->count(5)->create(['tenant_id' => $other->id]);

    [, $token] = mintKey(['leads.read']);

    expect(apiGet($token)->assertOk()->json('meta.total'))->toBe(2);
});

it('cannot reach another workspace record by its uuid', function (): void {
    $other = Tenant::factory()->create();
    $foreign = Lead::factory()->create(['tenant_id' => $other->id]);

    [, $token] = mintKey(['leads.read']);

    // The uuid is a real identifier, just not one this key may resolve.
    apiGet($token, "/api/v1/leads/{$foreign->uuid}")->assertNotFound();
});

// --- Logging ----------------------------------------------------------------

it('logs each authenticated request against the key', function (): void {
    [$key, $token] = mintKey();

    apiGet($token)->assertOk();

    $this->assertDatabaseHas('api_requests', [
        'api_key_id' => $key->id,
        'method' => 'GET',
        // The route pattern, not the resolved path, so usage reports group
        // rather than listing a million distinct urls.
        'path' => 'api/v1/leads',
        'status' => 200,
    ]);
});

it('does not log an unauthenticated request against any key', function (): void {
    $this->getJson('/api/v1/leads')->assertUnauthorized();

    $this->assertDatabaseCount('api_requests', 0);
});

// --- Health -----------------------------------------------------------------

it('leaves the health endpoint open', function (): void {
    $this->getJson('/api/v1/health')->assertOk()->assertJson(['status' => 'ok']);
});
