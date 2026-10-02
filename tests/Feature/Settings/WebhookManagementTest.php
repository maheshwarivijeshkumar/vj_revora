<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Domain\Webhooks\Enums\DeliveryStatus;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Jobs\DeliverWebhook;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Webhook management
|--------------------------------------------------------------------------
|
| §49, §50. Viewing the log and changing what is delivered are separate
| permissions, because a support engineer needs the first without the second.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    Queue::fake();

    $this->seed(PermissionSeeder::class);

    $manager = Role::findOrCreate('Integrator', 'web');
    $manager->syncPermissions(['webhook.view', 'webhook.manage']);

    $viewer = Role::findOrCreate('Support', 'web');
    $viewer->syncPermissions(['webhook.view']);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($manager);

    $this->viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->viewer->assignRole($viewer);
});

function webhookProps(): array
{
    return test()->actingAs(test()->user)
        ->get('/settings/webhooks')
        ->assertOk()
        ->viewData('page')['props'];
}

// --- Access -----------------------------------------------------------------

it('needs webhook.view to see the page', function (): void {
    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($stranger)->get('/settings/webhooks')->assertForbidden();
});

it('lets a viewer read the log but not change what is delivered', function (): void {
    $endpoint = WebhookEndpoint::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->viewer)->get('/settings/webhooks')->assertOk();

    $this->actingAs($this->viewer)
        ->post('/settings/webhooks', [
            'description' => 'Mine',
            'url' => 'https://example.com/hook',
            'events' => ['lead.created'],
        ])
        ->assertForbidden();

    $this->actingAs($this->viewer)
        ->delete("/settings/webhooks/{$endpoint->uuid}")
        ->assertForbidden();

    expect(WebhookEndpoint::count())->toBe(1);
});

// --- Listing -----------------------------------------------------------------

it('lists endpoints with their delivery count and signing details', function (): void {
    WebhookEndpoint::factory()->create([
        'tenant_id' => $this->tenant->id,
        'description' => 'Warehouse sync',
    ]);

    $props = webhookProps();

    expect($props['endpoints'])->toHaveCount(1)
        ->and($props['endpoints'][0]['description'])->toBe('Warehouse sync')
        // Addressed by uuid, so an internal id never appears in a URL.
        ->and($props['endpoints'][0]['id'])->toBe(WebhookEndpoint::sole()->uuid)
        ->and($props['endpoints'][0]['deliveries_count'])->toBe(0)
        // The subscriber needs the secret itself to verify a signature.
        ->and($props['endpoints'][0]['secret'])->toStartWith('whsec_')
        ->and($props['signature']['header'])->toBe('X-Revora-Signature');
});

it('shows only this workspace\'s endpoints', function (): void {
    WebhookEndpoint::factory()->create(['tenant_id' => $this->tenant->id]);

    $other = Tenant::factory()->create();
    app(TenantContext::class)->runAs(
        $other,
        fn () => WebhookEndpoint::factory()->count(3)->create(['tenant_id' => $other->id]),
    );

    expect(webhookProps()['endpoints'])->toHaveCount(1);
});

it('does not offer an event nothing emits yet', function (): void {
    $offered = array_column(webhookProps()['events'], 'value');

    // Messaging, appointments and automations arrive in later phases; a
    // subscription to them would sit silent and look like a broken integration.
    expect($offered)->toContain('lead.created')
        ->and($offered)->not->toContain('message.received')
        ->and($offered)->not->toContain('automation.failed')
        ->and($offered)->not->toContain('subscription.updated');
});

// --- Creating ----------------------------------------------------------------

it('creates an endpoint with a generated signing secret', function (): void {
    $this->actingAs($this->user)
        ->from('/settings/webhooks')
        ->post('/settings/webhooks', [
            'description' => 'Warehouse sync',
            'url' => 'https://example.com/hooks/revora',
            'events' => ['lead.created', 'deal.won'],
        ])
        ->assertRedirect('/settings/webhooks')
        ->assertSessionHas('success');

    $endpoint = WebhookEndpoint::sole();

    expect($endpoint->events)->toBe(['lead.created', 'deal.won'])
        ->and($endpoint->secret)->toStartWith('whsec_')
        ->and($endpoint->is_active)->toBeTrue()
        ->and($endpoint->created_by)->toBe($this->user->id);
});

it('refuses a plain http url', function (): void {
    $this->actingAs($this->user)
        ->from('/settings/webhooks')
        ->post('/settings/webhooks', [
            'description' => 'Insecure',
            'url' => 'http://example.com/hook',
            'events' => ['lead.created'],
        ])
        ->assertSessionHasErrors('url');

    // A signature proves who sent a payload, not that nobody read it, and these
    // carry personal data (§88).
    expect(WebhookEndpoint::count())->toBe(0);
});

it('requires a description and at least one event', function (): void {
    $this->actingAs($this->user)
        ->from('/settings/webhooks')
        ->post('/settings/webhooks', ['description' => '', 'url' => '', 'events' => []])
        ->assertSessionHasErrors(['description', 'url', 'events']);
});

it('refuses to subscribe to an event that does not exist yet', function (): void {
    $this->actingAs($this->user)
        ->from('/settings/webhooks')
        ->post('/settings/webhooks', [
            'description' => 'Early',
            'url' => 'https://example.com/hook',
            'events' => ['message.received'],
        ])
        ->assertSessionHasErrors('events.0');

    expect(WebhookEndpoint::count())->toBe(0);
});

// --- Updating ----------------------------------------------------------------

it('pauses and resumes an endpoint', function (): void {
    $endpoint = WebhookEndpoint::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->user)
        ->patch("/settings/webhooks/{$endpoint->uuid}", ['is_active' => false]);

    expect($endpoint->refresh()->isDeliverable())->toBeFalse();

    $this->actingAs($this->user)
        ->patch("/settings/webhooks/{$endpoint->uuid}", ['is_active' => true]);

    expect($endpoint->refresh()->isDeliverable())->toBeTrue();
});

it('clears an automatic retirement when the endpoint is resumed', function (): void {
    $endpoint = WebhookEndpoint::factory()->disabled()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->user)
        ->patch("/settings/webhooks/{$endpoint->uuid}", ['is_active' => true]);

    $endpoint->refresh();

    // Otherwise the switch appears to do nothing: the endpoint reads as active
    // while disabled_at still blocks every delivery.
    expect($endpoint->disabled_at)->toBeNull()
        ->and($endpoint->consecutive_failures)->toBe(0)
        ->and($endpoint->isDeliverable())->toBeTrue();
});

it('changes the subscribed events', function (): void {
    $endpoint = WebhookEndpoint::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->user)
        ->patch("/settings/webhooks/{$endpoint->uuid}", ['events' => ['deal.lost']]);

    expect($endpoint->refresh()->events)->toBe(['deal.lost']);
});

it('cannot touch another workspace\'s endpoint', function (): void {
    $other = Tenant::factory()->create();

    $foreign = app(TenantContext::class)->runAs(
        $other,
        fn () => WebhookEndpoint::factory()->create(['tenant_id' => $other->id]),
    );

    $this->actingAs($this->user)
        ->patch("/settings/webhooks/{$foreign->uuid}", ['is_active' => false])
        ->assertNotFound();

    expect($foreign->refresh()->is_active)->toBeTrue();
});

// --- Test send ---------------------------------------------------------------

it('queues a test delivery a subscriber can verify against', function (): void {
    $endpoint = WebhookEndpoint::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->user)
        ->from('/settings/webhooks')
        ->post("/settings/webhooks/{$endpoint->uuid}/test")
        ->assertSessionHas('success');

    $delivery = WebhookDelivery::sole();

    // A real event shape rather than an invented webhook.test, so the
    // subscriber exercises the handler they will actually use.
    expect($delivery->event)->toBe(WebhookEvent::LeadCreated)
        ->and($delivery->payload['data']['is_test'])->toBeTrue();

    Queue::assertPushed(DeliverWebhook::class);
});

it('refuses to test a disabled endpoint instead of silently doing nothing', function (): void {
    $endpoint = WebhookEndpoint::factory()->disabled()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->user)
        ->from('/settings/webhooks')
        ->post("/settings/webhooks/{$endpoint->uuid}/test")
        ->assertSessionHas('error');

    expect(WebhookDelivery::count())->toBe(0);
});

// --- Replay ------------------------------------------------------------------

it('replays a settled delivery', function (): void {
    $endpoint = WebhookEndpoint::factory()->create(['tenant_id' => $this->tenant->id]);

    $original = WebhookDelivery::create([
        'webhook_endpoint_id' => $endpoint->id,
        'event' => WebhookEvent::LeadCreated,
        'payload' => ['event' => 'lead.created', 'data' => ['id' => 'x']],
        'status' => DeliveryStatus::Failed,
    ]);

    $this->actingAs($this->user)
        ->from('/settings/webhooks')
        ->post("/settings/webhooks/deliveries/{$original->uuid}/replay")
        ->assertSessionHas('success');

    expect(WebhookDelivery::count())->toBe(2);

    $replay = WebhookDelivery::query()->where('replay_of_id', $original->id)->sole();

    // Equality, not identity: a JSON round trip does not promise key order.
    expect($replay->payload)->toEqual($original->payload);
});

it('offers replay only for a delivery that has settled', function (): void {
    $endpoint = WebhookEndpoint::factory()->create(['tenant_id' => $this->tenant->id]);

    foreach ([DeliveryStatus::Delivered, DeliveryStatus::Retrying] as $status) {
        WebhookDelivery::create([
            'webhook_endpoint_id' => $endpoint->id,
            'event' => WebhookEvent::LeadCreated,
            'payload' => ['data' => []],
            'status' => $status,
        ]);
    }

    $replayable = collect(webhookProps()['deliveries'])->keyBy('status');

    // One still retrying will come round again on its own; offering a replay
    // would invite a duplicate send.
    expect($replayable['delivered']['can_replay'])->toBeTrue()
        ->and($replayable['retrying']['can_replay'])->toBeFalse();
});

// --- Removal -----------------------------------------------------------------

it('soft deletes an endpoint so the log stays readable', function (): void {
    $endpoint = WebhookEndpoint::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->user)
        ->delete("/settings/webhooks/{$endpoint->uuid}")
        ->assertSessionHas('success');

    expect(WebhookEndpoint::count())->toBe(0)
        ->and(WebhookEndpoint::withTrashed()->count())->toBe(1);
});
