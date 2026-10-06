<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Creating and editing a contact from the UI
|--------------------------------------------------------------------------
|
| §21 employment is a relationship, §42 the server decides, §18 someone already
| on file is enriched rather than duplicated.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    Queue::fake();

    $this->seed(PermissionSeeder::class);

    $role = Role::findOrCreate('Rep', 'web');
    $role->syncPermissions([
        'contact.view', 'contact.create', 'contact.update', 'contact.delete',
    ]);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);
});

function postContact(array $overrides = []): TestResponse
{
    return test()->actingAs(test()->user)
        ->from('/contacts')
        ->post('/contacts', [
            'first_name' => 'Amara',
            'last_name' => 'Okafor',
            'email' => 'amara@acme.example',
            ...$overrides,
        ]);
}

// --- Access ---------------------------------------------------------------------

it('gates create, edit and delete separately', function (): void {
    $viewer = Role::findOrCreate('Viewer', 'web');
    $viewer->syncPermissions(['contact.view']);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole($viewer);

    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($user)->post('/contacts', ['email' => 'x@y.example'])
        ->assertForbidden();
    $this->actingAs($user)->patch("/contacts/{$contact->id}", ['email' => 'x@y.example'])
        ->assertForbidden();
    $this->actingAs($user)->delete("/contacts/{$contact->id}")->assertForbidden();
});

// --- Creating -------------------------------------------------------------------

it('creates a contact and composes the full name', function (): void {
    postContact()
        ->assertRedirect('/contacts')
        ->assertSessionHas('success');

    $contact = Contact::sole();

    expect($contact->full_name)->toBe('Amara Okafor')
        ->and($contact->email_normalized)->toBe('amara@acme.example');
});

it('enriches someone already on file rather than duplicating them', function (): void {
    postContact();

    postContact(['email' => 'AMARA@acme.example', 'job_title' => 'CTO'])
        ->assertSessionHas('warning');

    // Said out loud: silently merging would leave the rep looking for a row
    // that was never created.
    expect(Contact::count())->toBe(1)
        ->and(Contact::sole()->job_title)->toBe('CTO');
});

it('attaches an existing company and records the role', function (): void {
    $company = Company::factory()->create(['tenant_id' => $this->tenant->id]);

    postContact(['company_id' => $company->id, 'company_role' => 'Procurement lead']);

    $contact = Contact::sole();
    $attached = $contact->companies()->sole();

    expect($attached->id)->toBe($company->id)
        ->and($attached->pivot->role)->toBe('Procurement lead')
        // First employer becomes the current one; there is nothing to displace.
        ->and($attached->pivot->is_primary)->toBeTrue();
});

it('creates a company that was typed rather than chosen', function (): void {
    postContact(['company_name' => 'Northwind Trading']);

    expect(Company::sole()->name)->toBe('Northwind Trading')
        ->and(Contact::sole()->companies()->count())->toBe(1);
});

it('reuses a company whose name was typed again', function (): void {
    Company::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Acme']);

    postContact(['company_name' => 'acme']);

    // Two Acmes on the board is a data problem no reporting can recover from.
    expect(Company::count())->toBe(1);
});

// --- Validation -----------------------------------------------------------------

it('refuses a contact with neither an email nor a phone', function (): void {
    postContact(['email' => '', 'phone' => ''])->assertSessionHasErrors('email');

    expect(Contact::count())->toBe(0);
});

it('refuses both an existing company and a typed one', function (): void {
    $company = Company::factory()->create(['tenant_id' => $this->tenant->id]);

    // Attach the chosen one, or create the typed one and attach that? Rather
    // than guess, it asks.
    postContact(['company_id' => $company->id, 'company_name' => 'Northwind'])
        ->assertSessionHasErrors('company_name');
});

it('refuses a role with no company to hold it', function (): void {
    postContact(['company_role' => 'Procurement lead'])
        ->assertSessionHasErrors('company_role');
});

it('validates formats on the server', function (): void {
    postContact(['email' => 'not-an-email'])->assertSessionHasErrors('email');
    postContact(['country' => 'United Arab Emirates'])->assertSessionHasErrors('country');
});

it('refuses an owner or a company from another workspace', function (): void {
    $other = Tenant::factory()->create();

    $stranger = User::factory()->create(['tenant_id' => $other->id]);
    $foreignCompany = app(TenantContext::class)->runAs(
        $other,
        fn () => Company::factory()->create(['tenant_id' => $other->id]),
    );

    // `exists` queries the table directly, so the global scope never runs and
    // this has to be checked explicitly (ADR-009).
    postContact(['owner_id' => $stranger->id])->assertSessionHasErrors('owner_id');
    postContact(['company_id' => $foreignCompany->id])
        ->assertSessionHasErrors('company_id');
});

// --- Editing ---------------------------------------------------------------------

it('updates the fields the form offers', function (): void {
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->user)
        ->from('/contacts')
        ->patch("/contacts/{$contact->id}", [
            'first_name' => 'Renamed',
            'last_name' => 'Person',
            'email' => 'renamed@acme.example',
            'job_title' => 'CFO',
        ])
        ->assertRedirect('/contacts')
        ->assertSessionHas('success');

    expect($contact->refresh()->full_name)->toBe('Renamed Person')
        ->and($contact->job_title)->toBe('CFO');
});

it('does not reshuffle which employer is current', function (): void {
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);
    $current = Company::factory()->create(['tenant_id' => $this->tenant->id]);
    $another = Company::factory()->create(['tenant_id' => $this->tenant->id]);

    $contact->companies()->attach($current->id, ['is_primary' => true]);

    $this->actingAs($this->user)->patch("/contacts/{$contact->id}", [
        'email' => $contact->email,
        'company_id' => $another->id,
    ]);

    // §21. Someone who has moved on keeps their history, and an edit must not
    // silently decide which job is the current one.
    $primary = $contact->companies()->wherePivot('is_primary', true)->sole();

    expect($primary->id)->toBe($current->id)
        ->and($contact->companies()->count())->toBe(2);
});

it('can be edited without naming a company at all', function (): void {
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->user)
        ->patch("/contacts/{$contact->id}", [
            'email' => $contact->email,
            'job_title' => 'CFO',
        ])
        ->assertSessionHasNoErrors();

    expect($contact->refresh()->companies()->count())->toBe(0);
});

it('cannot touch a contact in another workspace', function (): void {
    $other = Tenant::factory()->create();

    $foreign = app(TenantContext::class)->runAs(
        $other,
        fn () => Contact::factory()->create(['tenant_id' => $other->id]),
    );

    $this->actingAs($this->user)
        ->patch("/contacts/{$foreign->id}", ['email' => 'x@y.example'])
        ->assertNotFound();

    $this->actingAs($this->user)->delete("/contacts/{$foreign->id}")->assertNotFound();
});

// --- Deleting ---------------------------------------------------------------------

it('soft deletes so an accidental delete is recoverable', function (): void {
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->user)
        ->from('/contacts')
        ->delete("/contacts/{$contact->id}")
        ->assertSessionHas('success');

    expect(Contact::count())->toBe(0)
        ->and(Contact::withTrashed()->count())->toBe(1);
});

// --- Audited like every other write -----------------------------------------------

it('audits the creation against the person who did it', function (): void {
    postContact();

    $log = AuditLog::query()->where('action', 'contact.created')->sole();

    expect($log->actor_id)->toBe($this->user->id);
});
