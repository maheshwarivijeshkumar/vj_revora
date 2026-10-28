<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Models\Tenant;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Tenders page
|--------------------------------------------------------------------------
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

function tenderRefs(array $query = []): array
{
    $props = test()->actingAs(test()->user)
        ->get('/tenders?'.http_build_query($query))
        ->assertOk()
        ->viewData('page')['props'];

    return array_column($props['tenders']['data'], 'reference');
}

it('requires the lead.view permission', function (): void {
    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($stranger)->get('/tenders')->assertForbidden();
});

it('shows open web, software and IT tenders by default, soonest deadline first', function (): void {
    Tender::factory()->create(['reference' => 'LATER', 'closing_at' => now()->addDays(20)]);
    Tender::factory()->create(['reference' => 'SOONER', 'closing_at' => now()->addDays(2)]);
    Tender::factory()->other()->create(['reference' => 'FURNITURE']);
    Tender::factory()->closed()->create(['reference' => 'CLOSED']);

    expect(tenderRefs())->toBe(['SOONER', 'LATER']);
});

it('widens to other tenders and closed ones on request', function (): void {
    Tender::factory()->create(['reference' => 'SOFTWARE']);
    Tender::factory()->other()->create(['reference' => 'FURNITURE']);
    Tender::factory()->closed()->create(['reference' => 'CLOSED']);

    expect(tenderRefs(['topic' => ['other']]))->toBe(['FURNITURE'])
        ->and(tenderRefs(['show' => 'all']))->toContain('CLOSED')
        ->and(tenderRefs(['topic' => 'all']))->toContain('FURNITURE', 'SOFTWARE');
});

it('filters by buyer type and sorts by organization', function (): void {
    Tender::factory()->create([
        'reference' => 'PUB',
        'organization' => 'Ministry of Health',
        'buyer_kind' => 'public',
        'closing_at' => now()->addDays(5),
    ]);
    Tender::factory()->create([
        'reference' => 'SEMI',
        'organization' => 'Capital Development Authority',
        'buyer_kind' => 'semi_government',
        'closing_at' => now()->addDays(3),
    ]);
    Tender::factory()->create([
        'reference' => 'PVT',
        'organization' => 'Acme (Pvt) Ltd',
        'buyer_kind' => 'private',
        'closing_at' => now()->addDays(4),
    ]);

    expect(tenderRefs(['buyer' => 'semi_government']))->toBe(['SEMI'])
        ->and(tenderRefs(['buyer' => 'private']))->toBe(['PVT'])
        ->and(tenderRefs(['buyer' => 'not-a-buyer']))->toContain('PUB', 'SEMI', 'PVT')
        ->and(tenderRefs(['sort' => 'organization', 'direction' => 'asc']))->toBe(['PVT', 'SEMI', 'PUB']);
});

it('filters the list to one government', function (): void {
    Tender::factory()->create(['reference' => 'PK', 'source' => 'ppra', 'country' => 'Pakistan']);
    Tender::factory()->create([
        'reference' => 'UK',
        'source' => 'find-a-tender',
        'country' => 'United Kingdom',
        'title' => 'Council website development',
    ]);

    expect(tenderRefs(['source' => 'find-a-tender']))->toBe(['UK'])
        ->and(tenderRefs(['country' => 'Pakistan']))->toBe(['PK']);
});

it('starts a sync for the portals that were chosen', function (): void {
    Permission::findOrCreate('lead.update', 'web');
    $this->user->givePermissionTo('lead.update');

    Process::fake();

    $this->actingAs($this->user)
        ->post('/tenders/sync', ['sources' => ['ppra', 'not-a-portal']])
        ->assertRedirect()
        ->assertSessionHas('success');

    Process::assertRan(function (PendingProcess $process): bool {
        $command = implode(' ', (array) $process->command);

        return str_contains($command, 'tenders:fetch')
            && str_contains($command, '--source=ppra')
            && ! str_contains($command, 'not-a-portal');
    });
});

it('refuses a sync from someone who cannot update leads', function (): void {
    Process::fake();

    $this->actingAs($this->user)
        ->post('/tenders/sync', ['sources' => ['ppra']])
        ->assertForbidden();

    Process::assertNothingRan();
});

it('searches by title, tender number and organization', function (): void {
    Tender::factory()->create(['reference' => 'TS1', 'title' => 'Hospital management system', 'organization' => 'Ministry of Health']);
    Tender::factory()->create(['reference' => 'TS2', 'title' => 'Tax portal', 'organization' => 'FBR']);

    expect(tenderRefs(['search' => 'hospital']))->toBe(['TS1'])
        ->and(tenderRefs(['search' => 'TS2']))->toBe(['TS2'])
        ->and(tenderRefs(['search' => 'FBR']))->toBe(['TS2']);
});
