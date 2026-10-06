<?php

declare(strict_types=1);

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\LeadScorer;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Models\AuditLog;
use App\Models\Lead;
use App\Models\LeadScoreRule;
use App\Models\LeadSource;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Creating and editing a lead from the UI
|--------------------------------------------------------------------------
|
| §42 server-authoritative validation, §19 scoring on save, §88 consent with a
| date, and the same observers an API write fires.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    Queue::fake();

    $this->seed(PermissionSeeder::class);

    foreach (LeadScorer::defaultRules() as $i => $rule) {
        LeadScoreRule::create([...$rule, 'sort_order' => $i]);
    }

    $role = Role::findOrCreate('Rep', 'web');
    $role->syncPermissions(['lead.view', 'lead.create', 'lead.update', 'lead.delete']);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);
});

function postLead(array $overrides = []): TestResponse
{
    return test()->actingAs(test()->user)
        ->from('/leads')
        ->post('/leads', [
            'first_name' => 'Amara',
            'last_name' => 'Okafor',
            'email' => 'amara@acme.example',
            'status' => 'new',
            ...$overrides,
        ]);
}

// --- Access -------------------------------------------------------------------

it('needs lead.create to add one', function (): void {
    $viewer = Role::findOrCreate('Viewer', 'web');
    $viewer->syncPermissions(['lead.view']);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole($viewer);

    $this->actingAs($user)
        ->post('/leads', ['email' => 'nope@acme.example', 'status' => 'new'])
        ->assertForbidden();

    expect(Lead::count())->toBe(0);
});

it('needs lead.update to edit and lead.delete to remove', function (): void {
    $viewer = Role::findOrCreate('Viewer', 'web');
    $viewer->syncPermissions(['lead.view']);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole($viewer);

    $lead = Lead::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($user)->patch("/leads/{$lead->id}", ['status' => 'contacted'])
        ->assertForbidden();
    $this->actingAs($user)->delete("/leads/{$lead->id}")->assertForbidden();

    expect(Lead::count())->toBe(1);
});

// --- Creating ------------------------------------------------------------------

it('creates a lead and composes the full name', function (): void {
    postLead()
        ->assertRedirect('/leads')
        ->assertSessionHas('success');

    $lead = Lead::sole();

    expect($lead->full_name)->toBe('Amara Okafor')
        ->and($lead->status)->toBe(LeadStatus::New)
        ->and($lead->email_normalized)->toBe('amara@acme.example')
        ->and($lead->last_activity_at)->not->toBeNull();
});

it('scores a manually entered lead so it does not sit at zero', function (): void {
    postLead(['phone' => '+971501234567', 'company_name' => 'Acme']);

    // 10 email + 10 phone + 10 company. §19: the score carries an explanation,
    // and "we never looked" is not one.
    expect(Lead::sole()->score)->toBe(30);
});

it('rescores on edit, because an added phone changes the answer', function (): void {
    postLead();
    $lead = Lead::sole();
    $before = $lead->score;

    $this->actingAs($this->user)->patch("/leads/{$lead->id}", [
        'email' => 'amara@acme.example',
        'phone' => '+971501234567',
        'company_name' => 'Acme',
        'status' => 'new',
    ])->assertRedirect();

    expect($lead->refresh()->score)->toBeGreaterThan($before);
});

it('records consent with a date and where it came from', function (): void {
    postLead(['consent' => true]);

    $lead = Lead::sole();

    // §88. "They agreed" without a date is not evidence of anything.
    expect($lead->consent)->toBeTrue()
        ->and($lead->consent_at)->not->toBeNull()
        ->and($lead->consent_source)->toBe('manual-entry');
});

it('leaves consent unrecorded when the box is not ticked', function (): void {
    postLead();

    expect(Lead::sole()->consent)->toBeFalse()
        ->and(Lead::sole()->consent_at)->toBeNull();
});

it('normalises an empty field to null rather than storing an empty string', function (): void {
    postLead(['phone' => '', 'company_name' => '  ']);

    // Stored as "", `email = ''` would match the next blank lead during
    // deduplication (§18).
    expect(Lead::sole()->phone)->toBeNull()
        ->and(Lead::sole()->company_name)->toBeNull();
});

it('assigns an owner and a source when given', function (): void {
    $owner = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $source = LeadSource::factory()->create(['tenant_id' => $this->tenant->id]);

    postLead(['owner_id' => $owner->id, 'lead_source_id' => $source->id]);

    expect(Lead::sole()->owner_id)->toBe($owner->id)
        ->and(Lead::sole()->lead_source_id)->toBe($source->id);
});

// --- Validation -----------------------------------------------------------------

it('refuses a lead with neither an email nor a phone', function (): void {
    postLead(['email' => '', 'phone' => ''])
        ->assertSessionHasErrors('email');

    expect(Lead::count())->toBe(0);
});

it('accepts a lead identified only by phone', function (): void {
    postLead(['email' => '', 'phone' => '+971501234567'])
        ->assertSessionHas('success');
});

it('validates formats on the server, whatever the browser allowed', function (): void {
    postLead(['email' => 'not-an-email'])->assertSessionHasErrors('email');
    postLead(['country' => 'United Arab Emirates'])->assertSessionHasErrors('country');
    postLead(['status' => 'teapot'])->assertSessionHasErrors('status');
    postLead(['next_follow_up_at' => 'next tuesday'])
        ->assertSessionHasErrors('next_follow_up_at');

    expect(Lead::count())->toBe(0);
});

it('refuses an owner from another workspace', function (): void {
    $other = Tenant::factory()->create();
    $stranger = User::factory()->create(['tenant_id' => $other->id]);

    // `exists` runs through the tenant-scoped connection, so an id from
    // elsewhere does not resolve and the message says so rather than 500ing.
    postLead(['owner_id' => $stranger->id])->assertSessionHasErrors('owner_id');
});

it('requires a status', function (): void {
    postLead(['status' => ''])->assertSessionHasErrors('status');
});

// --- Editing ---------------------------------------------------------------------

it('updates the fields the form offers', function (): void {
    $lead = Lead::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->user)
        ->from('/leads')
        ->patch("/leads/{$lead->id}", [
            'first_name' => 'Renamed',
            'last_name' => 'Person',
            'email' => 'renamed@acme.example',
            'job_title' => 'CFO',
            'status' => 'contacted',
        ])
        ->assertRedirect('/leads')
        ->assertSessionHas('success');

    $lead->refresh();

    expect($lead->full_name)->toBe('Renamed Person')
        ->and($lead->job_title)->toBe('CFO')
        ->and($lead->status)->toBe(LeadStatus::Contacted);
});

it('stamps qualified_at once and does not reset it on a later edit', function (): void {
    $lead = Lead::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->user)->patch("/leads/{$lead->id}", [
        'email' => $lead->email,
        'status' => 'qualified',
    ]);

    $first = $lead->refresh()->qualified_at;

    $this->travel(1)->hour();

    $this->actingAs($this->user)->patch("/leads/{$lead->id}", [
        'email' => $lead->email,
        'status' => 'contacted',
    ]);
    $this->actingAs($this->user)->patch("/leads/{$lead->id}", [
        'email' => $lead->email,
        'status' => 'qualified',
    ]);

    expect($lead->refresh()->qualified_at->toIso8601String())
        ->toBe($first->toIso8601String());
});

it('will not let the form overwrite the score', function (): void {
    $lead = Lead::factory()->scored(20)->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'scored@acme.example',
        'phone' => null,
        'company_name' => null,
        'consent' => false,
    ]);

    $this->actingAs($this->user)->patch("/leads/{$lead->id}", [
        'email' => 'scored@acme.example',
        'status' => 'new',
        'score' => 99,
    ]);

    // The score carries an explanation; a form that could set it directly would
    // make that explanation untrue (§19). 10 is the email rule alone, which is
    // what the scorer actually computes for this record.
    expect($lead->refresh()->score)->toBe(10);
});

it('cannot touch a lead in another workspace', function (): void {
    $other = Tenant::factory()->create();

    $foreign = app(TenantContext::class)->runAs(
        $other,
        fn () => Lead::factory()->create(['tenant_id' => $other->id]),
    );

    $this->actingAs($this->user)
        ->patch("/leads/{$foreign->id}", ['email' => 'x@y.example', 'status' => 'new'])
        ->assertNotFound();

    $this->actingAs($this->user)->delete("/leads/{$foreign->id}")->assertNotFound();
});

// --- Deleting ---------------------------------------------------------------------

it('soft deletes so an accidental delete is recoverable', function (): void {
    $lead = Lead::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->user)
        ->from('/leads')
        ->delete("/leads/{$lead->id}")
        ->assertRedirect('/leads')
        ->assertSessionHas('success');

    expect(Lead::count())->toBe(0)
        ->and(Lead::withTrashed()->count())->toBe(1);
});

// --- Same path as every other write -----------------------------------------------

it('fires the observers an api write fires', function (): void {
    WebhookEndpoint::factory()
        ->subscribedTo([WebhookEvent::LeadCreated])
        ->create(['tenant_id' => $this->tenant->id]);

    postLead();

    // A lead typed into the form is a lead: it is audited and announced exactly
    // as one that arrived over the API.
    expect(WebhookDelivery::query()->where('event', 'lead.created')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'lead.created')->count())->toBe(1);
});

it('names the actor on the audit row', function (): void {
    postLead();

    expect(AuditLog::query()->where('action', 'lead.created')->sole()->actor_id)
        ->toBe($this->user->id);
});
