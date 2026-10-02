<?php

declare(strict_types=1);

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Leads list
|--------------------------------------------------------------------------
|
| Search, filtering, sorting and pagination all run in the database (§112).
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

    Permission::findOrCreate('lead.view', 'web');
    $role = Role::findOrCreate('Viewer', 'web');
    $role->syncPermissions(['lead.view']);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);
});

function leadProps(array $query = []): array
{
    return test()->actingAs(test()->user)
        ->get('/leads?'.http_build_query($query))
        ->assertOk()
        ->viewData('page')['props'];
}

// --- Access -----------------------------------------------------------------

it('requires the lead.view permission', function (): void {
    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($stranger)->get('/leads')->assertForbidden();
});

// --- Listing ----------------------------------------------------------------

it('lists leads for the current workspace only', function (): void {
    Lead::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    $other = Tenant::factory()->create();
    Lead::factory()->count(5)->create(['tenant_id' => $other->id]);

    expect(leadProps()['leads']['total'])->toBe(3);
});

it('hides duplicates that were merged away', function (): void {
    $master = Lead::factory()->create(['tenant_id' => $this->tenant->id]);
    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'merged_into_id' => $master->id,
    ]);

    // The merged row still exists so its history survives, but counting it
    // would show the same person twice (§18).
    expect(leadProps()['leads']['total'])->toBe(1);
});

it('paginates server-side rather than shipping every row', function (): void {
    Lead::factory()->count(30)->create(['tenant_id' => $this->tenant->id]);

    $leads = leadProps(['per_page' => 10])['leads'];

    expect($leads['data'])->toHaveCount(10)
        ->and($leads['total'])->toBe(30)
        ->and($leads['last_page'])->toBe(3);
});

it('ignores a page size that is not offered', function (): void {
    Lead::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

    // Otherwise ?per_page=100000 is a trivial way to exhaust memory.
    expect(leadProps(['per_page' => 100000])['leads']['per_page'])->toBe(25);
});

// --- Search (§111) ----------------------------------------------------------

it('searches across name, email, phone and company', function (): void {
    Lead::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Amara', 'last_name' => 'Okafor', 'email' => 'amara@acme.example', 'company_name' => 'Acme']);
    Lead::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Daniel', 'last_name' => 'Reyes', 'email' => 'daniel@other.example', 'company_name' => 'Other Ltd']);

    expect(leadProps(['search' => 'Amara'])['leads']['total'])->toBe(1)
        ->and(leadProps(['search' => 'acme.example'])['leads']['total'])->toBe(1)
        ->and(leadProps(['search' => 'Other Ltd'])['leads']['total'])->toBe(1)
        ->and(leadProps(['search' => 'nobody'])['leads']['total'])->toBe(0);
});

it('treats wildcard characters as literal text', function (): void {
    Lead::factory()->create(['tenant_id' => $this->tenant->id, 'company_name' => 'Acme']);

    // An unescaped % would match every row, which looks like a data leak to
    // anyone watching and is a trivial thing to type by accident.
    expect(leadProps(['search' => '%'])['leads']['total'])->toBe(0);
});

it('does not let a search term escape an active filter', function (): void {
    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'company_name' => 'Acme',
        'status' => LeadStatus::Won,
    ]);

    // Without grouping the OR, the search would return this row despite the
    // status filter excluding it.
    expect(leadProps(['search' => 'Acme', 'status' => ['new']])['leads']['total'])->toBe(0);
});

// --- Filters ----------------------------------------------------------------

it('filters by status, owner and score range', function (): void {
    $owner = User::factory()->create(['tenant_id' => $this->tenant->id]);

    Lead::factory()->scored(90)->create(['tenant_id' => $this->tenant->id, 'status' => LeadStatus::Qualified, 'owner_id' => $owner->id]);
    Lead::factory()->scored(20)->create(['tenant_id' => $this->tenant->id, 'status' => LeadStatus::New]);

    expect(leadProps(['status' => ['qualified']])['leads']['total'])->toBe(1)
        ->and(leadProps(['owner_id' => [$owner->id]])['leads']['total'])->toBe(1)
        ->and(leadProps(['score_min' => 50])['leads']['total'])->toBe(1)
        ->and(leadProps(['score_max' => 50])['leads']['total'])->toBe(1);
});

it('accepts a filter value as a scalar or an array', function (): void {
    Lead::factory()->create(['tenant_id' => $this->tenant->id, 'status' => LeadStatus::Qualified]);

    // A query string delivers one selection as a scalar and several as an
    // array, so both shapes have to work.
    expect(leadProps(['status' => 'qualified'])['leads']['total'])->toBe(1)
        ->and(leadProps(['status' => ['qualified']])['leads']['total'])->toBe(1);
});

// --- Sorting ----------------------------------------------------------------

it('sorts by an allowed column', function (): void {
    Lead::factory()->scored(10)->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Low', 'last_name' => 'Score']);
    Lead::factory()->scored(90)->create(['tenant_id' => $this->tenant->id, 'first_name' => 'High', 'last_name' => 'Score']);

    $descending = leadProps(['sort' => 'score', 'direction' => 'desc'])['leads']['data'];
    $ascending = leadProps(['sort' => 'score', 'direction' => 'asc'])['leads']['data'];

    expect($descending[0]['score'])->toBe(90)
        ->and($ascending[0]['score'])->toBe(10);
});

it('ignores a sort column that is not allowed', function (): void {
    Lead::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    // Sorting by an arbitrary column name is a straightforward way to probe
    // the schema, so unknown columns fall back to the default.
    expect(leadProps(['sort' => 'password', 'direction' => 'asc'])['filters']['sort'])
        ->toBe('created_at');
});

// --- Row shape --------------------------------------------------------------

it('marks whether a lead came from an authorized provider api', function (): void {
    $connected = LeadSource::factory()->connected()->create(['tenant_id' => $this->tenant->id]);
    $imported = LeadSource::factory()->imported()->create(['tenant_id' => $this->tenant->id]);

    Lead::factory()->create(['tenant_id' => $this->tenant->id, 'lead_source_id' => $connected->id]);
    Lead::factory()->create(['tenant_id' => $this->tenant->id, 'lead_source_id' => $imported->id]);

    $rows = collect(leadProps(['sort' => 'created_at', 'direction' => 'asc'])['leads']['data']);

    // §2 requires the UI to distinguish an authorized API connection from an
    // uploaded list, so the flag has to reach the row.
    expect($rows->firstWhere('source', $connected->name)['source_authorized'])->toBeTrue()
        ->and($rows->firstWhere('source', $imported->name)['source_authorized'])->toBeFalse();
});
