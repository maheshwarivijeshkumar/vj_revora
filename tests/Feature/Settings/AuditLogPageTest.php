<?php

declare(strict_types=1);

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Tenancy\TenantContext;
use App\Models\ApiKey;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Reading the audit trail
|--------------------------------------------------------------------------
|
| §54. Read-only, filtered in the database, and never showing another
| workspace's entries.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    Queue::fake();

    $this->seed(PermissionSeeder::class);

    $role = Role::findOrCreate('Auditor', 'web');
    $role->syncPermissions(['audit.view']);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);
});

function auditProps(array $query = []): array
{
    return test()->actingAs(test()->user)
        ->get('/settings/audit?'.http_build_query($query))
        ->assertOk()
        ->viewData('page')['props'];
}

// --- Access -------------------------------------------------------------------

it('requires the audit.view permission', function (): void {
    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($stranger)->get('/settings/audit')->assertForbidden();
});

it('offers no way to change or remove an entry', function (): void {
    app(AuditRecorder::class)->record(AuditAction::BrandingChanged);

    // A trail somebody can tidy up is not evidence of anything, so there is no
    // route to tidy it with.
    $this->actingAs($this->user)->delete('/settings/audit/1')->assertNotFound();
    $this->actingAs($this->user)->patch('/settings/audit/1')->assertNotFound();
});

// --- Listing ------------------------------------------------------------------

it('lists newest first, because a trail is read backwards from an incident', function (): void {
    Contact::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'First']);
    $this->travel(1)->minute();
    Contact::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Second']);

    $entries = auditProps()['logs']['data'];

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['after']['first_name'])->toBe('Second');
});

it('names a user, an api key and the system differently', function (): void {
    $key = ApiKey::mint('Zapier', ['contacts.read'])['key'];

    app(AuditRecorder::class)->record(AuditAction::BrandingChanged, actor: $this->user);
    app(AuditRecorder::class)->record(AuditAction::BrandingChanged, actor: $key);
    app(AuditRecorder::class)->record(AuditAction::BrandingChanged);

    $actors = collect(auditProps()['logs']['data'])->pluck('actor.name');

    expect($actors->all())->toEqualCanonicalizing([
        $this->user->name,
        'Zapier (API key)',
        'System',
    ]);
});

it('marks a destructive action so it stands out', function (): void {
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);
    $contact->delete();

    $entries = collect(auditProps()['logs']['data'])->keyBy('action');

    // "What was deleted and by whom" is the question this page is opened for.
    expect($entries['contact.deleted']['is_destructive'])->toBeTrue()
        ->and($entries['contact.created']['is_destructive'])->toBeFalse();
});

it('shows only this workspace\'s entries', function (): void {
    Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    $other = Tenant::factory()->create();
    app(TenantContext::class)->runAs(
        $other,
        fn () => Contact::factory()->count(3)->create(['tenant_id' => $other->id]),
    );

    // AuditLog is central with a nullable tenant_id, so the filter is applied by
    // hand rather than by the global scope. That is exactly why it is tested.
    expect(auditProps()['logs']['meta']['total'])->toBe(1);
});

it('paginates rather than returning the whole history', function (): void {
    Contact::factory()->count(60)->create(['tenant_id' => $this->tenant->id]);

    $logs = auditProps()['logs'];

    expect($logs['data'])->toHaveCount(50)
        ->and($logs['meta']['total'])->toBe(60)
        ->and($logs['meta']['last_page'])->toBe(2);
});

// --- Filtering ----------------------------------------------------------------

it('filters by action', function (): void {
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);
    $contact->delete();

    expect(auditProps(['action' => ['contact.deleted']])['logs']['meta']['total'])->toBe(1);
});

it('filters by who did it', function (): void {
    $other = User::factory()->create(['tenant_id' => $this->tenant->id]);

    app(AuditRecorder::class)->record(AuditAction::BrandingChanged, actor: $this->user);
    app(AuditRecorder::class)->record(AuditAction::BrandingChanged, actor: $other);

    expect(auditProps(['actor' => $this->user->id])['logs']['meta']['total'])->toBe(1);
});

it('includes everything that happened on the to date', function (): void {
    app(AuditRecorder::class)->record(AuditAction::BrandingChanged);

    $today = now()->toDateString();

    // A filter that excludes everything on the day you asked for is a filter
    // nobody trusts twice.
    expect(auditProps(['from' => $today, 'to' => $today])['logs']['meta']['total'])->toBe(1);
});

it('rejects an action that is not in the catalogue', function (): void {
    $this->actingAs($this->user)
        ->get('/settings/audit?action[]=lead.vanished')
        ->assertSessionHasErrors('action.0');
});

it('rejects an unparseable date rather than ignoring it', function (): void {
    $this->actingAs($this->user)
        ->get('/settings/audit?from=last-tuesday')
        ->assertSessionHasErrors('from');
});
