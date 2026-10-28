<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Models\CrewMember;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    Permission::findOrCreate('company.delete', 'web');
});

function grantCrewDelete(): void
{
    $role = Role::findOrCreate('Operations-crew-delete', 'web');
    $role->syncPermissions(['company.delete']);

    test()->user = User::factory()->create(['tenant_id' => test()->tenant->id]);
    test()->user->assignRole($role);
}

it('soft deletes only the crew members that were confirmed', function (): void {
    grantCrewDelete();

    $first = CrewMember::query()->create(['name' => 'Ada']);
    $second = CrewMember::query()->create(['name' => 'Bea']);
    $kept = CrewMember::query()->create(['name' => 'Cara']);

    $this->actingAs($this->user)
        ->from('/crew')
        ->post('/operations/bulk-delete', [
            'resource' => 'crew',
            'ids' => [$first->id, $second->id],
        ])
        ->assertRedirect('/crew')
        ->assertSessionHas('success', '2 records deleted.');

    expect(CrewMember::query()->whereKey($kept->id)->exists())->toBeTrue()
        ->and(CrewMember::query()->whereKey([$first->id, $second->id])->exists())->toBeFalse();

    $this->assertSoftDeleted('crew_members', ['id' => $first->id]);
    $this->assertSoftDeleted('crew_members', ['id' => $second->id]);
});

it('leaves another workspace untouched when those ids are submitted', function (): void {
    grantCrewDelete();

    $other = Tenant::factory()->create();
    $foreignId = app(TenantContext::class)->runAs(
        $other,
        fn (): int => CrewMember::query()->create(['name' => 'Other crew'])->id,
    );

    $this->actingAs($this->user)
        ->from('/crew')
        ->post('/operations/bulk-delete', [
            'resource' => 'crew',
            'ids' => [$foreignId],
        ])
        ->assertRedirect('/crew')
        ->assertSessionHas('error', 'Nothing was deleted.');

    app(TenantContext::class)->runAs($other, function () use ($foreignId): void {
        expect(CrewMember::query()->whereKey($foreignId)->exists())->toBeTrue();
    });
});

it('refuses a list the person is not allowed to delete', function (string $resource): void {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($user)
        ->post('/operations/bulk-delete', [
            'resource' => $resource,
            'ids' => [1],
        ])
        ->assertForbidden();
})->with([
    'clients',
    'projects',
    'estimates',
    'invoices',
    'change-orders',
    'schedule',
    'crew',
    'subcontractors',
    'materials',
    'documents',
]);

it('rejects a list that cannot be deleted', function (): void {
    grantCrewDelete();

    $this->actingAs($this->user)
        ->post('/operations/bulk-delete', [
            'resource' => 'messages',
            'ids' => [1],
        ])
        ->assertNotFound();
});

it('requires at least one record before deleting', function (): void {
    grantCrewDelete();

    $this->actingAs($this->user)
        ->from('/crew')
        ->post('/operations/bulk-delete', [
            'resource' => 'crew',
            'ids' => [],
        ])
        ->assertRedirect('/crew')
        ->assertSessionHasErrors('ids');
});

it('sends a guest to sign in', function (): void {
    $this->post('/operations/bulk-delete', [
        'resource' => 'crew',
        'ids' => [1],
    ])->assertRedirect(route('login'));
});
