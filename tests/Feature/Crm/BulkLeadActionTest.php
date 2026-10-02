<?php

declare(strict_types=1);

use App\Domain\Bulk\Actions\RunBulkLeadAction;
use App\Domain\Bulk\Enums\BulkStatus;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Jobs\ProcessBulkOperation;
use App\Models\AuditLog;
use App\Models\BulkOperation;
use App\Models\Lead;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Bulk actions on leads
|--------------------------------------------------------------------------
|
| §113. Permission-checked per action, tenant-isolated, confirmed for
| destructive operations, queued when large, audited, and summarised.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    Queue::fake();

    $this->seed(PermissionSeeder::class);

    $role = Role::findOrCreate('Manager', 'web');
    $role->syncPermissions([
        'lead.view', 'lead.update', 'lead.assign', 'lead.delete',
    ]);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);
});

function bulk(array $payload): TestResponse
{
    return test()->actingAs(test()->user)
        ->from('/leads')
        ->post('/leads/bulk', $payload);
}

function seedLeads(int $count = 3): array
{
    return Lead::factory()
        ->count($count)
        ->create(['tenant_id' => test()->tenant->id])
        ->pluck('id')
        ->all();
}

// --- Permissions ---------------------------------------------------------------

it('checks the permission the action needs, not a blanket bulk permission', function (): void {
    $limited = Role::findOrCreate('Editor', 'web');
    $limited->syncPermissions(['lead.view', 'lead.update']);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole($limited);

    $ids = seedLeads();

    // May change a status, may not delete or reassign.
    $this->actingAs($user)->from('/leads')
        ->post('/leads/bulk', ['action' => 'change_status', 'ids' => $ids, 'status' => 'contacted'])
        ->assertRedirect();

    $this->actingAs($user)->post('/leads/bulk', ['action' => 'delete', 'ids' => $ids])
        ->assertForbidden();

    $this->actingAs($user)
        ->post('/leads/bulk', ['action' => 'assign', 'ids' => $ids, 'owner_id' => $user->id])
        ->assertForbidden();

    expect(Lead::count())->toBe(3);
});

// --- Assign ---------------------------------------------------------------------

it('assigns many leads to one owner', function (): void {
    $ids = seedLeads();
    $owner = User::factory()->create(['tenant_id' => $this->tenant->id]);

    bulk(['action' => 'assign', 'ids' => $ids, 'owner_id' => $owner->id])
        ->assertRedirect('/leads')
        ->assertSessionHas('success');

    expect(Lead::query()->where('owner_id', $owner->id)->count())->toBe(3);
});

it('treats an empty owner as unassign rather than as a missing argument', function (): void {
    $owner = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $ids = Lead::factory()->count(2)
        ->create(['tenant_id' => $this->tenant->id, 'owner_id' => $owner->id])
        ->pluck('id')->all();

    bulk(['action' => 'assign', 'ids' => $ids, 'owner_id' => null])
        ->assertSessionHas('success');

    expect(Lead::query()->whereNotNull('owner_id')->count())->toBe(0);
});

it('refuses an owner from another workspace', function (): void {
    $ids = seedLeads(1);
    $other = Tenant::factory()->create();
    $stranger = User::factory()->create(['tenant_id' => $other->id]);

    bulk(['action' => 'assign', 'ids' => $ids, 'owner_id' => $stranger->id]);

    // Counted as skipped, with a reason, rather than assigned across a boundary.
    $operation = BulkOperation::sole();

    expect($operation->failed)->toBe(1)
        ->and($operation->errors[0])->toContain('not in this workspace')
        ->and(Lead::query()->whereNotNull('owner_id')->count())->toBe(0);
});

// --- Status ---------------------------------------------------------------------

it('changes status and stamps qualified_at only the first time', function (): void {
    $ids = seedLeads(2);

    bulk(['action' => 'change_status', 'ids' => $ids, 'status' => 'qualified']);

    $first = Lead::query()->firstOrFail()->qualified_at;

    $this->travel(1)->hour();
    bulk(['action' => 'change_status', 'ids' => $ids, 'status' => 'contacted']);
    bulk(['action' => 'change_status', 'ids' => $ids, 'status' => 'qualified']);

    // A bulk requalify must not reset time-to-qualify across the selection.
    expect(Lead::query()->firstOrFail()->qualified_at->toIso8601String())
        ->toBe($first->toIso8601String())
        ->and(Lead::query()->where('status', LeadStatus::Qualified)->count())->toBe(2);
});

it('rejects a status that does not exist', function (): void {
    bulk(['action' => 'change_status', 'ids' => seedLeads(), 'status' => 'teapot'])
        ->assertSessionHasErrors('status');
});

it('names the missing argument rather than failing silently', function (): void {
    bulk(['action' => 'change_status', 'ids' => seedLeads()])
        ->assertSessionHasErrors('status');

    bulk(['action' => 'add_tag', 'ids' => seedLeads()])
        ->assertSessionHasErrors('tag');
});

// --- Tags ------------------------------------------------------------------------

it('creates the tag once and applies it to every lead', function (): void {
    $ids = seedLeads(3);

    bulk(['action' => 'add_tag', 'ids' => $ids, 'tag' => 'Trade show 2027'])
        ->assertSessionHas('success');

    $tag = Tag::sole();

    expect($tag->name)->toBe('Trade show 2027')
        ->and($tag->slug)->toBe('trade-show-2027')
        ->and($tag->leads()->count())->toBe(3);
});

it('reuses an existing tag rather than creating a near-duplicate', function (): void {
    Tag::create(['name' => 'VIP']);

    bulk(['action' => 'add_tag', 'ids' => seedLeads(), 'tag' => 'vip']);

    expect(Tag::count())->toBe(1);
});

it('applying the same tag twice is not an error', function (): void {
    $ids = seedLeads(2);

    bulk(['action' => 'add_tag', 'ids' => $ids, 'tag' => 'VIP']);
    bulk(['action' => 'add_tag', 'ids' => $ids, 'tag' => 'VIP'])
        ->assertSessionHas('success');

    expect(Tag::sole()->leads()->count())->toBe(2);
});

it('removes a tag', function (): void {
    $ids = seedLeads(2);

    bulk(['action' => 'add_tag', 'ids' => $ids, 'tag' => 'VIP']);
    bulk(['action' => 'remove_tag', 'ids' => $ids, 'tag' => 'VIP']);

    expect(Tag::sole()->leads()->count())->toBe(0);
});

// --- Delete ----------------------------------------------------------------------

it('soft deletes so an administrator can restore', function (): void {
    bulk(['action' => 'delete', 'ids' => seedLeads(3)])
        ->assertSessionHas('success');

    expect(Lead::count())->toBe(0)
        ->and(Lead::withTrashed()->count())->toBe(3);
});

// --- Going through the model layer ------------------------------------------------

it('fires the same observers a single edit would', function (): void {
    WebhookEndpoint::factory()
        ->subscribedTo([WebhookEvent::LeadUpdated])
        ->create(['tenant_id' => $this->tenant->id]);

    $ids = seedLeads(3);

    bulk(['action' => 'change_status', 'ids' => $ids, 'status' => 'contacted']);

    // A mass UPDATE would be faster and wrong: no audit row, no webhook, no
    // normalised columns. A bulk edit has to mean the same as the same edit made
    // one row at a time.
    expect(WebhookDelivery::query()->where('event', 'lead.updated')->count())->toBe(3)
        ->and(AuditLog::query()->where('action', 'lead.updated')->count())
        // Three per-record rows plus one for the operation itself.
        ->toBe(4);
});

it('records the operation as one decision, not only as n edits', function (): void {
    bulk(['action' => 'delete', 'ids' => seedLeads(2)]);

    $log = AuditLog::query()
        ->where('entity_type', BulkOperation::class)
        ->sole();

    // Without this the trail shows two deletions and not the single decision
    // that caused them (§54).
    expect($log->after['bulk_action'])->toBe('delete')
        ->and($log->after['total'])->toBe(2)
        ->and($log->after['succeeded'])->toBe(2);
});

// --- Partial results --------------------------------------------------------------

it('drops an id that no longer exists before counting the total', function (): void {
    $ids = seedLeads(3);
    Lead::query()->whereKey($ids[0])->forceDelete();

    bulk(['action' => 'change_status', 'ids' => $ids, 'status' => 'contacted'])
        ->assertSessionHas('success');

    // Resolved up front, so the summary counts two rather than claiming three
    // and then reporting one skipped.
    expect(BulkOperation::sole()->total)->toBe(2)
        ->and(Lead::query()->where('status', LeadStatus::Contacted)->count())->toBe(2);
});

it('counts a row that vanished mid-run as skipped and explains why', function (): void {
    $ids = seedLeads(3);

    $operation = BulkOperation::create([
        'entity' => 'lead',
        'action' => 'change_status',
        'ids' => $ids,
        'payload' => ['status' => 'contacted'],
        'status' => BulkStatus::Pending,
        'total' => 3,
    ]);

    // The real race: deleted after the selection was taken, before the runner
    // reached its chunk. A long queued run makes this ordinary rather than rare.
    Lead::query()->whereKey($ids[0])->forceDelete();

    app(RunBulkLeadAction::class)->handle($operation);

    // Two of three is the right answer; refusing the lot is not.
    expect($operation->refresh()->status)->toBe(BulkStatus::PartiallyCompleted)
        ->and($operation->failed)->toBe(1)
        ->and($operation->errors[0])->toContain('no longer exists')
        ->and($operation->summary())->toContain('2 of 3')
        ->and(Lead::query()->where('status', LeadStatus::Contacted)->count())->toBe(2);
});

it('warns rather than claims success when the run was partial', function (): void {
    $ids = seedLeads(2);
    $other = Tenant::factory()->create();
    $stranger = User::factory()->create(['tenant_id' => $other->id]);

    // An owner outside the workspace is refused per record, so the operation
    // finishes partially and the flash has to say so.
    bulk(['action' => 'assign', 'ids' => $ids, 'owner_id' => $stranger->id])
        ->assertSessionHas('warning');
});

// --- Isolation ---------------------------------------------------------------------

it('ignores ids from another workspace entirely', function (): void {
    $mine = seedLeads(2);

    $other = Tenant::factory()->create();
    $theirs = app(TenantContext::class)->runAs(
        $other,
        fn () => Lead::factory()->count(2)->create(['tenant_id' => $other->id])->pluck('id')->all(),
    );

    bulk(['action' => 'delete', 'ids' => [...$mine, ...$theirs]]);

    // Not even counted in the total, so the summary cannot imply it touched them.
    expect(BulkOperation::sole()->total)->toBe(2)
        ->and(Lead::withTrashed()->whereIn('id', $theirs)->whereNotNull('deleted_at')->count())
        ->toBe(0);
});

it('says so when nothing in the selection is reachable', function (): void {
    $other = Tenant::factory()->create();
    $theirs = app(TenantContext::class)->runAs(
        $other,
        fn () => Lead::factory()->create(['tenant_id' => $other->id])->id,
    );

    bulk(['action' => 'delete', 'ids' => [$theirs]])
        ->assertSessionHas('error');

    expect(BulkOperation::count())->toBe(0);
});

// --- Queueing ----------------------------------------------------------------------

it('runs a small selection inline and queues a large one', function (): void {
    bulk(['action' => 'change_status', 'ids' => seedLeads(5), 'status' => 'contacted'])
        ->assertSessionHas('success');

    Queue::assertNothingPushed();

    $many = Lead::factory()
        ->count(BulkOperation::INLINE_LIMIT + 1)
        ->create(['tenant_id' => $this->tenant->id])
        ->pluck('id')->all();

    bulk(['action' => 'change_status', 'ids' => $many, 'status' => 'contacted'])
        // The uuid, so the page polls this operation rather than guessing.
        ->assertSessionHas('bulkOperation');

    Queue::assertPushed(ProcessBulkOperation::class);
});

it('caps the selection size', function (): void {
    bulk([
        'action' => 'delete',
        'ids' => range(1, BulkOperation::MAX_IDS + 1),
    ])->assertSessionHasErrors('ids');
});

// --- Progress ------------------------------------------------------------------------

it('reports progress by uuid', function (): void {
    $operation = BulkOperation::create([
        'entity' => 'lead',
        'action' => 'delete',
        'ids' => [1, 2, 3, 4],
        'status' => BulkStatus::Running,
        'total' => 4,
        'processed' => 1,
    ]);

    $body = $this->actingAs($this->user)
        ->getJson("/bulk-operations/{$operation->uuid}")
        ->assertOk()
        ->json();

    expect($body['progress'])->toBe(25)
        ->and($body['finished'])->toBeFalse()
        ->and($body['summary'])->toBeNull();
});

it('does not expose another workspace\'s operation', function (): void {
    $other = Tenant::factory()->create();

    $foreign = app(TenantContext::class)->runAs($other, fn () => BulkOperation::create([
        'entity' => 'lead',
        'action' => 'delete',
        'ids' => [1],
        'status' => BulkStatus::Running,
        'total' => 1,
    ]));

    $this->actingAs($this->user)
        ->getJson("/bulk-operations/{$foreign->uuid}")
        ->assertNotFound();
});
