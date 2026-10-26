<?php

declare(strict_types=1);

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\LeadsExport;
use App\Domain\Tenancy\TenantContext;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Leads export
|--------------------------------------------------------------------------
|
| The list, as filtered, downloaded as Excel (§70).
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

    Permission::findOrCreate('lead.view', 'web');
    Permission::findOrCreate('lead.export', 'web');
    $role = Role::findOrCreate('Exporter', 'web');
    $role->syncPermissions(['lead.view', 'lead.export']);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);
});

it('requires the lead.export permission', function (): void {
    $viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $viewer->assignRole(Role::findOrCreate('Viewer', 'web')->syncPermissions(['lead.view']));

    $this->actingAs($viewer)->get('/leads/export')->assertForbidden();
});

it('downloads only the leads matching the current filters, from this workspace', function (): void {
    $this->freezeTime();
    Excel::fake();

    $kept = Lead::factory()->create(['tenant_id' => $this->tenant->id, 'status' => LeadStatus::New]);
    Lead::factory()->create(['tenant_id' => $this->tenant->id, 'status' => LeadStatus::Lost]);

    $other = Tenant::factory()->create();
    Lead::factory()->create(['tenant_id' => $other->id, 'status' => LeadStatus::New]);

    $this->actingAs($this->user)->get('/leads/export?status[]=new')->assertOk();

    Excel::assertDownloaded(
        'leads-'.now()->format('Y-m-d-His').'.xlsx',
        fn (LeadsExport $export): bool => $export->query()->pluck('id')->all() === [$kept->id],
    );
});
