<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Companies list
|--------------------------------------------------------------------------
|
| §112. The counts are sorted in the database, because "our biggest accounts"
| is why this list gets opened and the browser can only sort the page it has.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    Queue::fake();

    Permission::findOrCreate('company.view', 'web');
    $role = Role::findOrCreate('Viewer', 'web');
    $role->syncPermissions(['company.view']);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);
});

function companyProps(array $query = []): array
{
    return test()->actingAs(test()->user)
        ->get('/companies?'.http_build_query($query))
        ->assertOk()
        ->viewData('page')['props'];
}

// --- Access -------------------------------------------------------------------

it('requires the company.view permission', function (): void {
    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($stranger)->get('/companies')->assertForbidden();
});

// --- Listing ------------------------------------------------------------------

it('lists companies for the current workspace only', function (): void {
    Company::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    $other = Tenant::factory()->create();
    Company::factory()->count(4)->create(['tenant_id' => $other->id]);

    expect(companyProps()['companies']['total'])->toBe(2);
});

it('counts people and deals per company', function (): void {
    $pipeline = Pipeline::factory()->withDefaultStages()->create(['tenant_id' => $this->tenant->id]);
    $company = Company::factory()->create(['tenant_id' => $this->tenant->id]);

    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);
    $company->contacts()->attach($contact->id);

    Deal::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'company_id' => $company->id,
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $pipeline->firstStage()->id,
    ]);

    $row = companyProps()['companies']['data'][0];

    expect($row['contacts_count'])->toBe(1)
        ->and($row['deals_count'])->toBe(2);
});

it('sorts by the people count in the database', function (): void {
    $small = Company::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Small']);
    $big = Company::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Big']);

    $big->contacts()->attach(
        Contact::factory()->count(3)->create(['tenant_id' => $this->tenant->id])->pluck('id'),
    );
    $small->contacts()->attach(
        Contact::factory()->create(['tenant_id' => $this->tenant->id])->id,
    );

    $sorted = companyProps(['sort' => 'contacts_count', 'direction' => 'desc']);

    expect($sorted['companies']['data'][0]['name'])->toBe('Big');
});

// --- Search -------------------------------------------------------------------

it('searches name, domain and website', function (): void {
    Company::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Northwind Trading',
        'website' => 'https://northwind.example',
    ]);
    Company::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    expect(companyProps(['search' => 'Northwind'])['companies']['total'])->toBe(1)
        ->and(companyProps(['search' => 'northwind.example'])['companies']['total'])->toBe(1);
});

it('treats a wildcard as a literal', function (): void {
    Company::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    expect(companyProps(['search' => '_'])['companies']['total'])->toBe(0);
});

// --- Filtering ----------------------------------------------------------------

it('filters by industry', function (): void {
    Company::factory()->create(['tenant_id' => $this->tenant->id, 'industry' => 'Logistics']);
    Company::factory()->create(['tenant_id' => $this->tenant->id, 'industry' => 'Retail']);

    expect(companyProps(['industry' => ['Logistics']])['companies']['total'])->toBe(1);
});

it('offers the industries this workspace actually uses', function (): void {
    Company::factory()->create(['tenant_id' => $this->tenant->id, 'industry' => 'Logistics']);
    Company::factory()->create(['tenant_id' => $this->tenant->id, 'industry' => null]);

    // Deferred, so it arrives on the follow-up partial request rather than with
    // the first paint (Inertia v3) — which is the point: the table renders
    // without waiting for filter options it does not draw.
    // The version is computed from the Vite manifest during the request, so it
    // is read from a first visit rather than guessed; a mismatch is answered
    // with a 409 asset-refresh, not the props.
    $version = $this->actingAs($this->user)
        ->get('/companies')
        ->assertOk()
        ->viewData('page')['version'];

    $props = $this->actingAs($this->user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'crm/companies/Index',
            'X-Inertia-Partial-Data' => 'options',
        ])
        ->get('/companies')
        ->assertOk()
        ->json('props');

    // Drawn from the data rather than a fixed list, so the filter matches what
    // is on the page.
    expect(array_column($props['options']['industries'], 'value'))->toBe(['Logistics']);
});

// --- Sorting ------------------------------------------------------------------

it('ignores a sort column it does not allow', function (): void {
    Company::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(companyProps(['sort' => 'secret'])['filters']['sort'])->toBe('created_at');
});
