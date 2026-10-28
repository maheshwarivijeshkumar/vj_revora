<?php

declare(strict_types=1);

use App\Domain\Deals\Enums\DealStatus;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Calendar
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

    Permission::findOrCreate('lead.view', 'web');
    Permission::findOrCreate('deal.view', 'web');
});

it('shows approved leads and won deals for this month', function (): void {
    grantCalendar(['lead.view', 'deal.view']);

    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Approved',
        'last_name' => 'Ada',
        'status' => LeadStatus::Qualified,
        'created_at' => now()->startOfMonth()->addDays(2),
    ]);
    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Booked',
        'last_name' => 'Bea',
        'status' => LeadStatus::Appointment,
        'created_at' => now()->startOfMonth()->addDays(3),
    ]);
    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'New',
        'last_name' => 'Ned',
        'status' => LeadStatus::New,
        'created_at' => now(),
    ]);
    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Lost',
        'last_name' => 'Lia',
        'status' => LeadStatus::Lost,
        'created_at' => now(),
    ]);
    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Last',
        'last_name' => 'Month',
        'status' => LeadStatus::Qualified,
        'created_at' => now()->subMonth()->startOfMonth(),
    ]);

    $pipeline = Pipeline::factory()->withDefaultStages()->create(['tenant_id' => $this->tenant->id]);
    $stageId = $pipeline->stages()->value('id');

    Deal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $stageId,
        'title' => 'Won website',
        'status' => DealStatus::Won,
        'closed_at' => now(),
    ]);
    Deal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $stageId,
        'title' => 'Lost pitch',
        'status' => DealStatus::Lost,
        'closed_at' => now(),
    ]);

    expect(calendarTitles())->toBe(['Approved Ada', 'Booked Bea', 'Won website']);
});

it('opens another month from the query', function (): void {
    grantCalendar(['lead.view']);

    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'September',
        'last_name' => 'Sam',
        'status' => LeadStatus::Proposal,
        'created_at' => '2026-09-12 10:00:00',
    ]);

    $props = test()->actingAs(test()->user)
        ->get('/calendar?month=2026-09')
        ->assertOk()
        ->viewData('page')['props'];

    expect($props['label'])->toBe('September 2026')
        ->and(calendarTitlesFrom($props))->toBe(['September Sam']);
});

it('hides leads from someone who can only see deals', function (): void {
    grantCalendar(['deal.view']);

    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Approved',
        'last_name' => 'Ada',
        'status' => LeadStatus::Qualified,
        'created_at' => now(),
    ]);

    $pipeline = Pipeline::factory()->withDefaultStages()->create(['tenant_id' => $this->tenant->id]);

    Deal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $pipeline->stages()->value('id'),
        'title' => 'Won website',
        'status' => DealStatus::Won,
        'closed_at' => now(),
    ]);

    expect(calendarTitles())->toBe(['Won website']);
});

it('refuses the calendar when the user can see neither', function (): void {
    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($stranger)->get('/calendar')->assertForbidden();
});

/**
 * @param  list<string>  $permissions
 */
function grantCalendar(array $permissions): void
{
    $role = Role::findOrCreate('Calendar-'.implode('-', $permissions), 'web');
    $role->syncPermissions($permissions);

    test()->user = User::factory()->create(['tenant_id' => test()->tenant->id]);
    test()->user->assignRole($role);
}

/**
 * @return list<string>
 */
function calendarTitles(): array
{
    $props = test()->actingAs(test()->user)
        ->get('/calendar')
        ->assertOk()
        ->viewData('page')['props'];

    return calendarTitlesFrom($props);
}

/**
 * @param  array<string, mixed>  $props
 * @return list<string>
 */
function calendarTitlesFrom(array $props): array
{
    $titles = [];

    foreach ($props['days'] as $day) {
        foreach ($day['events'] as $event) {
            $titles[] = $event['title'];
        }
    }

    return $titles;
}
