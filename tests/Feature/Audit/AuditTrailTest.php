<?php

declare(strict_types=1);

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Deals\Actions\CreateDeal;
use App\Domain\Deals\Actions\MoveDealToStage;
use App\Domain\Leads\Actions\CaptureLead;
use App\Domain\Leads\Services\LeadNormalizer;
use App\Domain\Leads\Services\LeadScorer;
use App\Domain\Tenancy\TenantContext;
use App\Models\ApiKey;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\LeadScoreRule;
use App\Models\LeadSource;
use App\Models\Pipeline;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| The audit trail
|--------------------------------------------------------------------------
|
| §54. Actor, workspace, action, entity, before, after, IP, user agent, time.
| §55. Credentials never reach it.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    Queue::fake();

    foreach (LeadScorer::defaultRules() as $i => $rule) {
        LeadScoreRule::create([...$rule, 'sort_order' => $i]);
    }

    LeadSource::factory()->create(['tenant_id' => $this->tenant->id, 'key' => 'api']);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

/**
 * @return list<string>
 */
function actions(): array
{
    return AuditLog::query()->orderBy('id')->pluck('action')->all();
}

function captureFor(array $payload): Lead
{
    $normalized = app(LeadNormalizer::class)->normalize($payload);

    return app(CaptureLead::class)
        ->handle($normalized, LeadSource::query()->firstOrFail())
        ->lead;
}

// --- Entity changes ----------------------------------------------------------

it('records a creation with the new state and no before', function (): void {
    $contact = Contact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'new@acme.example',
    ]);

    $log = AuditLog::sole();

    expect($log->action)->toBe('contact.created')
        ->and($log->entity_type)->toBe(Contact::class)
        ->and($log->entity_id)->toBe($contact->id)
        ->and($log->before)->toBeNull()
        ->and($log->after['email'])->toBe('new@acme.example')
        ->and($log->tenant_id)->toBe($this->tenant->id);
});

it('records an edit as a diff of only what changed', function (): void {
    $contact = Contact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'job_title' => 'Analyst',
    ]);

    $contact->forceFill(['job_title' => 'Director'])->save();

    $log = AuditLog::query()->where('action', 'contact.updated')->sole();

    // The whole row would make the trail unreadable; the question is what this
    // person changed.
    expect(array_keys($log->after))->toBe(['job_title'])
        ->and($log->before['job_title'])->toBe('Analyst')
        ->and($log->after['job_title'])->toBe('Director');
});

it('ignores a save that changed nothing of substance', function (): void {
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    $contact->touch();
    $contact->save();

    // A touch is not an edit, and recording it would bury the real ones.
    expect(actions())->toBe(['contact.created']);
});

it('records a deletion with the whole record that is now gone', function (): void {
    $company = Company::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Acme',
    ]);

    $company->delete();

    $log = AuditLog::query()->where('action', 'company.deleted')->sole();

    // "What was in the thing that is now gone" is why a deletion audit exists.
    expect($log->before['name'])->toBe('Acme')
        ->and($log->after)->toBeNull();
});

it('audits all four entities', function (): void {
    $pipeline = Pipeline::factory()->withDefaultStages()->create(['tenant_id' => $this->tenant->id]);

    Lead::factory()->create(['tenant_id' => $this->tenant->id]);
    Contact::factory()->create(['tenant_id' => $this->tenant->id]);
    Company::factory()->create(['tenant_id' => $this->tenant->id]);
    app(CreateDeal::class)->handle(['title' => 'Acme', 'value' => 1000], $pipeline);

    expect(actions())->toBe([
        'lead.created',
        'contact.created',
        'company.created',
        'deal.created',
    ]);
});

// --- The capture pipeline ----------------------------------------------------

it('records a captured lead once, not once per save', function (): void {
    captureFor(['email' => 'captured@acme.example', 'company' => 'Acme']);

    // The pipeline saves again to record the score and the owner. Auditing
    // those would file system work as edits nobody made, so the trail holds the
    // creation and the routing decision and nothing in between.
    expect(actions())->toBe(['lead.created', 'lead.assigned'])
        ->and(AuditLog::query()->where('action', 'lead.updated')->count())->toBe(0);
});

it('records assignment as its own action', function (): void {
    captureFor(['email' => 'routed@acme.example']);

    // Assignment is a single-column update, so the generic entity audit would
    // file it as "lead edited". Who a lead went to is the question asked.
    expect(actions())->toBe(['lead.created', 'lead.assigned']);

    $log = AuditLog::query()->where('action', 'lead.assigned')->sole();

    expect($log->after['owner_id'])->toBe($this->user->id);
});

it('records a stage move with the stage names, not ids', function (): void {
    $pipeline = Pipeline::factory()->withDefaultStages()->create(['tenant_id' => $this->tenant->id]);
    $stages = $pipeline->stages()->get()->keyBy('key');

    $deal = app(CreateDeal::class)->handle(['title' => 'Acme', 'value' => 1000], $pipeline);
    app(MoveDealToStage::class)->handle($deal, $stages['qualified']);

    $log = AuditLog::query()->where('action', 'deal.stage_changed')->sole();

    // A row read six months later must make sense without the pipeline open
    // beside it.
    expect($log->before['stage'])->toBe('New')
        ->and($log->after['stage'])->toBe('Qualified');
});

// --- The actor ---------------------------------------------------------------

it('names the signed-in user as the actor', function (): void {
    $this->actingAs($this->user);

    Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    $log = AuditLog::sole();

    expect($log->actor_type)->toBe(User::class)
        ->and($log->actor_id)->toBe($this->user->id);
});

it('names the api key as the actor, not whoever created it', function (): void {
    $minted = ApiKey::mint('Zapier', ['contacts.write'], $this->user->id);

    $this->withHeader('Authorization', 'Bearer '.$minted['token'])
        ->postJson('/api/v1/contacts', ['email' => 'viaapi@acme.example'])
        ->assertCreated();

    $log = AuditLog::query()->where('action', 'contact.created')->sole();

    // "The Zapier key did this" and "Amara did this" are different facts.
    expect($log->actor_type)->toBe(ApiKey::class)
        ->and($log->actor_id)->toBe($minted['key']->id);
});

it('records no actor for work with nobody behind it', function (): void {
    Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    $log = AuditLog::sole();

    // A queue job or a console command has no actor, and inventing one would
    // make the trail lie.
    expect($log->actor_type)->toBeNull()
        ->and($log->actor_id)->toBeNull();
});

it('captures the ip and user agent of a real request', function (): void {
    $this->actingAs($this->user)
        ->withHeader('User-Agent', 'Mozilla/5.0 (Test Runner)')
        ->patch('/leads', [])
        ->assertStatus(405);

    // No audit from a 405, so write one through a request that does change data.
    app(AuditRecorder::class)->record(AuditAction::BrandingChanged);

    $log = AuditLog::sole();

    expect($log->ip)->not->toBeNull();
});

// --- Redaction ---------------------------------------------------------------

it('never records a password, a hash or a signing secret', function (): void {
    app(AuditRecorder::class)->record(
        AuditAction::ApiKeyCreated,
        after: [
            'name' => 'Visible',
            'password' => 'hunter2',
            'password_confirmation' => 'hunter2',
            'hash' => 'abc123',
            'secret' => 'whsec_abc',
            'api_key_token' => 'rvk_x_y',
            'remember_token' => 'z',
        ],
    );

    $after = AuditLog::sole()->after;

    // Substring matching, because an exact-name list would miss the next secret
    // somebody adds.
    expect($after['name'])->toBe('Visible')
        ->and($after['password'])->toBe('[redacted]')
        ->and($after['password_confirmation'])->toBe('[redacted]')
        ->and($after['hash'])->toBe('[redacted]')
        ->and($after['secret'])->toBe('[redacted]')
        ->and($after['api_key_token'])->toBe('[redacted]')
        ->and($after['remember_token'])->toBe('[redacted]');
});

it('redacts inside a nested array too', function (): void {
    app(AuditRecorder::class)->record(
        AuditAction::WebhookUpdated,
        after: ['config' => ['url' => 'https://x.example', 'secret' => 'whsec_abc']],
    );

    $after = AuditLog::sole()->after;

    expect($after['config']['url'])->toBe('https://x.example')
        ->and($after['config']['secret'])->toBe('[redacted]');
});

it('does not record a webhook signing secret when an endpoint is created', function (): void {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

    $role = Role::findOrCreate('Integrator', 'web');
    $role->syncPermissions(['webhook.view', 'webhook.manage']);
    $this->user->assignRole($role);

    $this->actingAs($this->user)->post('/settings/webhooks', [
        'description' => 'Sync',
        'url' => 'https://example.com/hook',
        'events' => ['lead.created'],
    ]);

    $log = AuditLog::query()->where('action', 'webhook.created')->sole();

    expect($log->after['url'])->toBe('https://example.com/hook')
        ->and($log->after)->not->toHaveKey('secret');
});

it('records an api key creation without the key', function (): void {
    $this->seed(PermissionSeeder::class);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

    $role = Role::findOrCreate('Dev', 'web');
    $role->syncPermissions(['api.view', 'api.create', 'api.revoke']);
    $this->user->assignRole($role);

    $this->actingAs($this->user)->post('/settings/api-keys', [
        'name' => 'CI',
        'scopes' => ['leads.read'],
    ]);

    $log = AuditLog::query()->where('action', 'api_key.created')->sole();
    $token = session('newApiKey')['token'];

    // An audit trail must not become a second place the key can be read from.
    expect($log->after['name'])->toBe('CI')
        ->and($log->after['scopes'])->toBe(['leads.read'])
        ->and(json_encode($log->after))->not->toContain($token);
});

// --- Access ------------------------------------------------------------------

it('records a sign-in against the user workspace', function (): void {
    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'signin@acme.example',
        'password' => bcrypt('correct-horse'),
    ]);

    $this->post('/login', [
        'email' => 'signin@acme.example',
        'password' => 'correct-horse',
    ]);

    $log = AuditLog::query()->where('action', 'login')->sole();

    // Sign-in happens before tenant resolution, so without binding the user's
    // workspace the entry would be invisible to the people it concerns.
    expect($log->tenant_id)->toBe($this->tenant->id)
        ->and($log->actor_id)->toBe($user->id);
});

it('records a failed sign-in with the address but never the password', function (): void {
    User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'victim@acme.example',
        'password' => bcrypt('correct-horse'),
    ]);

    $this->post('/login', [
        'email' => 'victim@acme.example',
        'password' => 'wrong-password',
    ]);

    $log = AuditLog::query()->where('action', 'login.failed')->sole();

    // A run of these against one address is the signal that matters.
    expect($log->after['email'])->toBe('victim@acme.example')
        ->and(json_encode($log->after))->not->toContain('wrong-password');
});

it('records a sign-out', function (): void {
    $this->actingAs($this->user)->post('/logout');

    expect(AuditLog::query()->where('action', 'logout')->count())->toBe(1);
});

// --- Isolation ---------------------------------------------------------------

it('records against the workspace the change happened in', function (): void {
    $other = Tenant::factory()->create();

    app(TenantContext::class)->runAs(
        $other,
        fn () => Contact::factory()->create(['tenant_id' => $other->id]),
    );

    Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(AuditLog::query()->where('tenant_id', $this->tenant->id)->count())->toBe(1)
        ->and(AuditLog::query()->where('tenant_id', $other->id)->count())->toBe(1);
});
