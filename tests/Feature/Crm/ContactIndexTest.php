<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Contacts list
|--------------------------------------------------------------------------
|
| Search, filtering, sorting and pagination all run in the database (§112).
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    Queue::fake();

    Permission::findOrCreate('contact.view', 'web');
    $role = Role::findOrCreate('Viewer', 'web');
    $role->syncPermissions(['contact.view']);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);
});

function contactProps(array $query = []): array
{
    return test()->actingAs(test()->user)
        ->get('/contacts?'.http_build_query($query))
        ->assertOk()
        ->viewData('page')['props'];
}

// --- Access -------------------------------------------------------------------

it('requires the contact.view permission', function (): void {
    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($stranger)->get('/contacts')->assertForbidden();
});

// --- Listing ------------------------------------------------------------------

it('lists contacts for the current workspace only', function (): void {
    Contact::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    $other = Tenant::factory()->create();
    Contact::factory()->count(5)->create(['tenant_id' => $other->id]);

    expect(contactProps()['contacts']['total'])->toBe(3);
});

it('paginates in the database rather than shipping every row', function (): void {
    Contact::factory()->count(30)->create(['tenant_id' => $this->tenant->id]);

    $page = contactProps(['per_page' => 10]);

    expect($page['contacts']['data'])->toHaveCount(10)
        ->and($page['contacts']['total'])->toBe(30)
        ->and($page['contacts']['last_page'])->toBe(3);
});

it('ignores a page size it does not offer', function (): void {
    Contact::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    // Otherwise per_page=100000 is a denial of service with a query string.
    expect(contactProps(['per_page' => 9999])['contacts']['per_page'])->toBe(25);
});

it('shows the primary company and how many others there are', function (): void {
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);
    $current = Company::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Acme',
    ]);
    $former = Company::factory()->create(['tenant_id' => $this->tenant->id]);

    $contact->companies()->attach($current->id, ['is_primary' => true]);
    $contact->companies()->attach($former->id, ['is_primary' => false]);

    $row = contactProps()['contacts']['data'][0];

    // Someone who has worked at several places keeps that history (§21).
    expect($row['company'])->toBe('Acme')
        ->and($row['companies_count'])->toBe(2);
});

// --- Search -------------------------------------------------------------------

it('searches name, email, phone and role', function (): void {
    Contact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Amara',
        'last_name' => 'Okafor',
        'email' => 'amara@acme.example',
        'phone' => '+971501234567',
        'job_title' => 'Head of Growth',
    ]);
    Contact::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    expect(contactProps(['search' => 'Amara'])['contacts']['total'])->toBe(1)
        ->and(contactProps(['search' => 'amara@acme'])['contacts']['total'])->toBe(1)
        // A distinctive fragment: the factory gives every contact a +9715 prefix,
        // so a shorter term would match other rows by luck.
        ->and(contactProps(['search' => '501234567'])['contacts']['total'])->toBe(1)
        ->and(contactProps(['search' => 'Head of'])['contacts']['total'])->toBe(1);
});

it('treats a wildcard as a literal', function (): void {
    Contact::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    expect(contactProps(['search' => '%'])['contacts']['total'])->toBe(0);
});

it('applies a filter alongside a search rather than instead of it', function (): void {
    $owner = User::factory()->create(['tenant_id' => $this->tenant->id]);

    Contact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Match',
        'owner_id' => $owner->id,
    ]);
    Contact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Match',
        'owner_id' => null,
    ]);

    // Without grouping the search, the OR would escape the owner filter.
    expect(contactProps(['search' => 'Match', 'owner_id' => [$owner->id]])['contacts']['total'])
        ->toBe(1);
});

// --- Filtering ----------------------------------------------------------------

it('filters by company', function (): void {
    $company = Company::factory()->create(['tenant_id' => $this->tenant->id]);
    $attached = Contact::factory()->create(['tenant_id' => $this->tenant->id]);
    $attached->companies()->attach($company->id, ['is_primary' => true]);

    Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(contactProps(['company_id' => [$company->id]])['contacts']['total'])->toBe(1);
});

// --- Sorting ------------------------------------------------------------------

it('sorts by an allowed column and ignores anything else', function (): void {
    Contact::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Zoe', 'last_name' => '']);
    Contact::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Abe', 'last_name' => '']);

    $sorted = contactProps(['sort' => 'full_name', 'direction' => 'asc']);

    expect($sorted['contacts']['data'][0]['name'])->toBe('Abe')
        // An unknown column must not reach the query builder.
        ->and(contactProps(['sort' => 'password'])['filters']['sort'])->toBe('created_at');
});
