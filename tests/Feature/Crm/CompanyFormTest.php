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
| Creating and editing a company from the UI
|--------------------------------------------------------------------------
|
| §21 the domain is what matching compares, §18 an existing company is enriched
| rather than duplicated.
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
        'company.view', 'company.create', 'company.update', 'company.delete',
    ]);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);
});

function postCompany(array $overrides = []): TestResponse
{
    return test()->actingAs(test()->user)
        ->from('/companies')
        ->post('/companies', ['name' => 'Northwind Trading', ...$overrides]);
}

// --- Access ---------------------------------------------------------------------

it('gates create, edit and delete separately', function (): void {
    $viewer = Role::findOrCreate('Viewer', 'web');
    $viewer->syncPermissions(['company.view']);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole($viewer);

    $company = Company::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($user)->post('/companies', ['name' => 'Nope'])->assertForbidden();
    $this->actingAs($user)->patch("/companies/{$company->id}", ['name' => 'Nope'])
        ->assertForbidden();
    $this->actingAs($user)->delete("/companies/{$company->id}")->assertForbidden();
});

// --- Creating -------------------------------------------------------------------

it('creates a company and works the domain out from the website', function (): void {
    postCompany(['website' => 'https://www.Northwind.Example/about'])
        ->assertRedirect('/companies')
        ->assertSessionHas('success');

    $company = Company::sole();

    // The stored host is what matching compares, so it has to be stable
    // however the website was typed.
    expect($company->domain)->toBe('northwind.example')
        ->and($company->website)->toBe('https://www.Northwind.Example/about');
});

it('lets an explicit domain win over the website', function (): void {
    postCompany([
        'website' => 'https://northwind-group.example',
        'domain' => 'northwind.example',
    ]);

    expect(Company::sole()->domain)->toBe('northwind.example');
});

it('enriches a company already on file rather than duplicating it', function (): void {
    postCompany(['domain' => 'northwind.example']);

    postCompany(['name' => 'Northwind Trading Ltd', 'domain' => 'northwind.example', 'industry' => 'Logistics'])
        ->assertSessionHas('warning');

    expect(Company::count())->toBe(1)
        // Gap-filled, not overwritten: the name on file was put there by
        // somebody and a later payload is not automatically more correct.
        ->and(Company::sole()->name)->toBe('Northwind Trading')
        ->and(Company::sole()->industry)->toBe('Logistics');
});

// --- Validation -----------------------------------------------------------------

it('requires a name', function (): void {
    postCompany(['name' => ''])->assertSessionHasErrors('name');

    expect(Company::count())->toBe(0);
});

it('validates the website is a full address', function (): void {
    postCompany(['website' => 'northwind.example'])->assertSessionHasErrors('website');
});

it('validates the country code', function (): void {
    postCompany(['country' => 'United Arab Emirates'])->assertSessionHasErrors('country');
});

it('refuses an owner from another workspace', function (): void {
    $other = Tenant::factory()->create();
    $stranger = User::factory()->create(['tenant_id' => $other->id]);

    postCompany(['owner_id' => $stranger->id])->assertSessionHasErrors('owner_id');
});

// --- Editing ---------------------------------------------------------------------

it('updates in place rather than matching onto another record', function (): void {
    $company = Company::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Acme',
    ]);
    $other = Company::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Northwind',
    ]);

    $this->actingAs($this->user)
        ->from('/companies')
        ->patch("/companies/{$company->id}", ['name' => 'Northwind'])
        ->assertSessionHas('success');

    // An edit is about this record. Routing it through the matcher would have
    // silently retargeted it at the other one.
    expect($company->refresh()->name)->toBe('Northwind')
        ->and(Company::count())->toBe(2)
        ->and($other->refresh()->name)->toBe('Northwind');
});

it('renormalises the domain when the website changes', function (): void {
    $company = Company::factory()->create([
        'tenant_id' => $this->tenant->id,
        'domain' => 'old.example',
    ]);

    $this->actingAs($this->user)->patch("/companies/{$company->id}", [
        'name' => $company->name,
        'website' => 'https://www.new.example',
    ]);

    // Someone correcting the website expects matching to follow rather than
    // keep using the old host.
    expect($company->refresh()->domain)->toBe('new.example');
});

it('can clear the owner', function (): void {
    $owner = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $company = Company::factory()->create([
        'tenant_id' => $this->tenant->id,
        'owner_id' => $owner->id,
    ]);

    $this->actingAs($this->user)->patch("/companies/{$company->id}", [
        'name' => $company->name,
    ]);

    // "Nobody" has to be something the form can say.
    expect($company->refresh()->owner_id)->toBeNull();
});

it('cannot touch a company in another workspace', function (): void {
    $other = Tenant::factory()->create();

    $foreign = app(TenantContext::class)->runAs(
        $other,
        fn () => Company::factory()->create(['tenant_id' => $other->id]),
    );

    $this->actingAs($this->user)
        ->patch("/companies/{$foreign->id}", ['name' => 'Taken'])
        ->assertNotFound();

    $this->actingAs($this->user)->delete("/companies/{$foreign->id}")->assertNotFound();
});

// --- Deleting ---------------------------------------------------------------------

it('soft deletes and leaves the people attached to it alone', function (): void {
    $company = Company::factory()->create(['tenant_id' => $this->tenant->id]);
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);
    $company->contacts()->attach($contact->id);

    $this->actingAs($this->user)
        ->from('/companies')
        ->delete("/companies/{$company->id}")
        ->assertSessionHas('success');

    expect(Company::count())->toBe(0)
        ->and(Company::withTrashed()->count())->toBe(1)
        // Deleting an employer does not delete the employees.
        ->and(Contact::count())->toBe(1);
});

// --- Audited ----------------------------------------------------------------------

it('audits the creation against the person who did it', function (): void {
    postCompany();

    expect(AuditLog::query()->where('action', 'company.created')->sole()->actor_id)
        ->toBe($this->user->id);
});
