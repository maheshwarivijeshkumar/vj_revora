<?php

declare(strict_types=1);

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Global search
|--------------------------------------------------------------------------
|
| §45, §120. Two properties are not negotiable: results never cross a
| workspace, and an entity the user cannot view is not searched at all.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    Queue::fake();

    $this->seed(PermissionSeeder::class);

    $all = Role::findOrCreate('Everything', 'web');
    $all->syncPermissions(['lead.view', 'contact.view', 'company.view', 'deal.view']);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($all);
});

function search(string $term): array
{
    return test()->actingAs(test()->user)
        ->getJson('/search?q='.urlencode($term))
        ->assertOk()
        ->json('groups');
}

/**
 * @return array<string, list<string>>
 */
function titlesByGroup(string $term): array
{
    $out = [];

    foreach (search($term) as $group) {
        $out[$group['key']] = array_column($group['hits'], 'title');
    }

    return $out;
}

// --- Matching -----------------------------------------------------------------

it('finds a lead by name, email and company', function (): void {
    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Amara',
        'last_name' => 'Okafor',
        'email' => 'amara@northwind.example',
        'company_name' => 'Northwind Trading',
    ]);

    expect(titlesByGroup('Amara')['leads'])->toBe(['Amara Okafor'])
        ->and(titlesByGroup('northwind.example')['leads'])->toBe(['Amara Okafor'])
        ->and(titlesByGroup('Northwind Trading')['leads'])->toBe(['Amara Okafor']);
});

it('finds a lead by a phone number typed with spaces', function (): void {
    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Dialled',
        'phone' => '+971501234567',
    ]);

    // Matched on the normalised column, so how it was typed does not matter.
    expect(titlesByGroup('+971 50 123 4567'))->toHaveKey('leads');
});

it('finds contacts, companies and deals', function (): void {
    $pipeline = Pipeline::factory()->withDefaultStages()->create(['tenant_id' => $this->tenant->id]);

    Contact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Acme',
        'last_name' => 'Person',
    ]);
    Company::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Industries']);
    Deal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'title' => 'Acme renewal',
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $pipeline->firstStage()->id,
    ]);

    $groups = titlesByGroup('Acme');

    // Categorised, not interleaved: "the Acme company" and "the Acme deal" are
    // different answers to the same word.
    expect($groups['contacts'])->toBe(['Acme Person'])
        ->and($groups['companies'])->toBe(['Acme Industries'])
        ->and($groups['deals'])->toBe(['Acme renewal']);
});

it('finds a company by its domain', function (): void {
    Company::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Northwind',
        'domain' => 'northwind.example',
    ]);

    expect(titlesByGroup('northwind.ex')['companies'])->toBe(['Northwind']);
});

it('omits a group with no matches rather than returning it empty', function (): void {
    Company::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Lonely']);

    // An empty heading is a row the user has to read to learn nothing.
    expect(array_keys(titlesByGroup('Lonely')))->toBe(['companies']);
});

it('excludes merged duplicates', function (): void {
    $master = Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Primary',
        'last_name' => 'Record',
    ]);
    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Primary',
        'last_name' => 'Duplicate',
        'merged_into_id' => $master->id,
    ]);

    expect(titlesByGroup('Primary')['leads'])->toBe(['Primary Record']);
});

it('ranks leads by score so the hot one is first', function (): void {
    Lead::factory()->scored(10)->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Cold',
        'last_name' => 'Acme',
    ]);
    Lead::factory()->scored(90)->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Hot',
        'last_name' => 'Acme',
    ]);

    expect(titlesByGroup('Acme')['leads'][0])->toBe('Hot Acme');
});

it('caps each group so the palette stays scannable', function (): void {
    Lead::factory()->count(12)->create([
        'tenant_id' => $this->tenant->id,
        'company_name' => 'Acme',
    ]);

    expect(titlesByGroup('Acme')['leads'])->toHaveCount(5);
});

// --- Guard rails --------------------------------------------------------------

it('returns nothing for a term too short to mean anything', function (): void {
    Lead::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Al']);

    // One character matches most of the workspace, which is a list, not a
    // search result.
    expect(search('A'))->toBeEmpty();
});

it('treats a wildcard as a literal', function (): void {
    Company::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Acme']);

    // Unescaped, this would return the whole table and read as a bug.
    expect(search('%%'))->toBeEmpty();
});

it('requires a term', function (): void {
    $this->actingAs($this->user)
        ->getJson('/search')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
});

it('requires authentication', function (): void {
    $this->getJson('/search?q=acme')->assertUnauthorized();
});

// --- Permissions ---------------------------------------------------------------

it('does not search an entity the user cannot view', function (): void {
    $limited = Role::findOrCreate('LeadsOnly', 'web');
    $limited->syncPermissions(['lead.view']);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole($limited);

    Lead::factory()->create(['tenant_id' => $this->tenant->id, 'company_name' => 'Acme']);
    Company::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Industries']);
    Contact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Acme',
        'last_name' => 'Person',
    ]);

    $groups = $this->actingAs($user)->getJson('/search?q=Acme')->assertOk()->json('groups');

    // Not searched and filtered afterwards: never searched.
    expect(array_column($groups, 'key'))->toBe(['leads']);
});

it('returns nothing at all for a user with no view permissions', function (): void {
    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);

    Company::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Acme']);

    expect($this->actingAs($stranger)->getJson('/search?q=Acme')->assertOk()->json('groups'))
        ->toBeEmpty();
});

// --- Isolation ----------------------------------------------------------------

it('never returns another workspace\'s records', function (): void {
    $other = Tenant::factory()->create();

    app(TenantContext::class)->runAs($other, function () use ($other): void {
        Company::factory()->create(['tenant_id' => $other->id, 'name' => 'Acme Secret']);
        Lead::factory()->create(['tenant_id' => $other->id, 'company_name' => 'Acme Secret']);
    });

    Company::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Acme Ours']);

    expect(titlesByGroup('Acme'))->toBe(['companies' => ['Acme Ours']]);
});

// --- Result shape --------------------------------------------------------------

it('returns a link, a subtitle and a status for each hit', function (): void {
    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Amara',
        'last_name' => 'Okafor',
        'email' => 'amara@acme.example',
        'company_name' => 'Acme',
        'status' => LeadStatus::Qualified,
    ]);

    $hit = search('Amara')[0]['hits'][0];

    expect($hit['title'])->toBe('Amara Okafor')
        ->and($hit['subtitle'])->toBe('Acme · amara@acme.example')
        ->and($hit['badge'])->toBe('Qualified')
        ->and($hit['href'])->toStartWith('/leads?search=');
});

it('echoes the term back so a stale response can be discarded', function (): void {
    // The palette fires a request per debounce window; a slower earlier one must
    // not overwrite a newer answer.
    expect($this->actingAs($this->user)->getJson('/search?q=acme')->json('term'))
        ->toBe('acme');
});
