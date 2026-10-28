<?php

declare(strict_types=1);

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotification;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    $this->seed(PermissionSeeder::class);

    $role = Role::findOrCreate('Reader', 'web');
    $role->syncPermissions(['lead.view']);

    $this->actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->reader = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actor->assignRole($role);
    $this->reader->assignRole($role);
});

it('notifies people who may view the lead and leaves the notice unread', function (): void {
    $this->actingAs($this->actor);

    $lead = Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Hana',
        'last_name' => 'Sato',
    ]);
    $lead->status = LeadStatus::Contacted;
    $lead->save();

    $notice = UserNotification::query()->where('user_id', $this->reader->id)->first();

    expect($notice)->not->toBeNull()
        ->and($notice->read_at)->toBeNull()
        ->and($notice->title)->toBe('Lead status changed')
        ->and($notice->body)->toContain('Hana Sato')
        ->and($notice->body)->toContain('Contacted');

    expect(UserNotification::query()->where('user_id', $this->actor->id)->count())->toBe(0)
        ->and(UserNotification::query()->where('user_id', $this->stranger->id)->count())->toBe(0);
});

it('shows a notice only to the person it belongs to, until they read it', function (): void {
    $this->actingAs($this->actor);

    $lead = Lead::factory()->create(['tenant_id' => $this->tenant->id]);
    $lead->status = LeadStatus::Qualified;
    $lead->save();

    $notice = UserNotification::query()->where('user_id', $this->reader->id)->first();

    $readerProps = $this->actingAs($this->reader)
        ->get('/dashboard')
        ->assertOk()
        ->viewData('page')['props'];

    expect($readerProps['notifications']['unread'])->toBe(1)
        ->and($readerProps['notifications']['items'][0]['body'])->toBe($notice->body)
        ->and($readerProps['notifications']['items'][0]['read_at'])->toBeNull();

    $actorProps = $this->actingAs($this->actor)
        ->get('/dashboard')
        ->assertOk()
        ->viewData('page')['props'];

    expect($actorProps['notifications']['unread'])->toBe(0)
        ->and($actorProps['notifications']['items'])->toBe([]);

    $this->actingAs($this->actor)
        ->patch('/account/notifications/'.$notice->id)
        ->assertNotFound();

    expect($notice->fresh()->read_at)->toBeNull();

    $this->actingAs($this->reader)
        ->patch('/account/notifications/'.$notice->id)
        ->assertRedirect();

    expect($notice->fresh()->read_at)->not->toBeNull();
});

it('saves presence for the account menu', function (): void {
    $this->actingAs($this->actor)
        ->patch('/account/presence', ['presence' => 'busy'])
        ->assertRedirect();

    expect($this->actor->fresh()->presence)->toBe('busy');

    $this->actingAs($this->actor)
        ->patch('/account/presence', ['presence' => 'offline'])
        ->assertSessionHasErrors('presence');
});
