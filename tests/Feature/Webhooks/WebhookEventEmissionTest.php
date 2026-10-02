<?php

declare(strict_types=1);

use App\Domain\Deals\Actions\CreateDeal;
use App\Domain\Deals\Actions\MoveDealToStage;
use App\Domain\Leads\Actions\CaptureLead;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\LeadNormalizer;
use App\Domain\Leads\Services\LeadScorer;
use App\Domain\Tenancy\TenantContext;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\LeadScoreRule;
use App\Models\LeadSource;
use App\Models\Pipeline;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Which events actually fire
|--------------------------------------------------------------------------
|
| §49. The point of this file is coverage of the emission rules rather than of
| delivery: a subscriber must be told about a change whichever door it came
| through, and must not be told twice about one change.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    Queue::fake();

    foreach (LeadScorer::defaultRules() as $i => $rule) {
        LeadScoreRule::create([...$rule, 'sort_order' => $i]);
    }

    LeadSource::factory()->create(['tenant_id' => $this->tenant->id, 'key' => 'api']);

    // Subscribed to everything that exists, so a missing event shows up as an
    // absent delivery rather than as an unsubscribed endpoint.
    WebhookEndpoint::factory()
        ->subscribedTo(WebhookEvent::subscribable())
        ->create(['tenant_id' => $this->tenant->id]);
});

/**
 * @return list<string>
 */
function emitted(): array
{
    return WebhookDelivery::query()->orderBy('id')->pluck('event')->map(
        fn (WebhookEvent $event): string => $event->value,
    )->all();
}

function captureLead(array $payload): Lead
{
    $normalized = app(LeadNormalizer::class)->normalize($payload);

    return app(CaptureLead::class)->handle($normalized, LeadSource::query()->firstOrFail())->lead;
}

// --- Leads ------------------------------------------------------------------

it('emits lead.created once for a new lead', function (): void {
    captureLead(['email' => 'new@acme.example', 'company' => 'Acme']);

    // Not followed by a lead.updated: the pipeline's own saves while scoring
    // and routing are part of creating the lead, not edits to it.
    expect(emitted())->toBe(['lead.created']);
});

it('emits lead.assigned when capture routes the lead to someone', function (): void {
    User::factory()->create(['tenant_id' => $this->tenant->id]);

    captureLead(['email' => 'routed@acme.example']);

    expect(emitted())->toBe(['lead.created', 'lead.assigned']);
});

it('emits lead.assigned again when the lead is handed to someone else', function (): void {
    User::factory()->create(['tenant_id' => $this->tenant->id]);
    $other = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $lead = captureLead(['email' => 'routed@acme.example']);
    $lead->forceFill(['owner_id' => $other->id])->save();

    // Reassignment after the fact is the observer's job, and is announced with
    // the generic edit so a mirroring subscriber sees it too.
    expect(emitted())->toBe(['lead.created', 'lead.assigned', 'lead.assigned', 'lead.updated']);
});

it('does not announce a matched duplicate as a creation', function (): void {
    captureLead(['email' => 'dupe@acme.example']);
    captureLead(['email' => 'DUPE@acme.example', 'company' => 'Acme Ltd']);

    // Announcing it as created would have subscribers insert a second record.
    expect(emitted())->toBe(['lead.created', 'lead.updated'])
        ->and(Lead::count())->toBe(1);
});

it('stays silent when a duplicate changes nothing', function (): void {
    captureLead(['email' => 'same@acme.example', 'company' => 'Acme']);
    captureLead(['email' => 'same@acme.example', 'company' => 'Acme']);

    // "Nothing happened" is not an event.
    expect(emitted())->toBe(['lead.created']);
});

it('emits lead.qualified alongside lead.updated', function (): void {
    $lead = captureLead(['email' => 'q@acme.example']);

    $lead->forceFill(['status' => LeadStatus::Qualified])->save();

    // Both, not one: a subscriber mirroring leads subscribes to lead.updated
    // alone and would otherwise miss the qualify entirely.
    expect(emitted())->toBe(['lead.created', 'lead.qualified', 'lead.updated']);
});

it('emits lead.updated for an ordinary edit', function (): void {
    $lead = captureLead(['email' => 'edit@acme.example']);

    $lead->forceFill(['job_title' => 'CTO'])->save();

    expect(emitted())->toBe(['lead.created', 'lead.updated']);
});

it('emits nothing when a save changes nothing', function (): void {
    $lead = captureLead(['email' => 'noop@acme.example']);

    $lead->save();

    expect(emitted())->toBe(['lead.created']);
});

it('carries the same lead shape the rest api returns', function (): void {
    captureLead([
        'first_name' => 'Amara',
        'last_name' => 'Okafor',
        'email' => 'shape@acme.example',
    ]);

    $payload = WebhookDelivery::query()->sole()->payload;

    // One documented lead format, not two.
    expect($payload['data']['full_name'])->toBe('Amara Okafor')
        ->and($payload['data']['id'])->toBe(Lead::sole()->uuid)
        ->and($payload['data'])->toHaveKeys(['score', 'status', 'email'])
        ->and($payload['data'])->not->toHaveKey('tenant_id');
});

// --- Contacts ---------------------------------------------------------------

it('emits contact.created', function (): void {
    Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(emitted())->toBe(['contact.created']);
});

// --- Deals ------------------------------------------------------------------

it('emits deal.created when a deal is opened', function (): void {
    $pipeline = Pipeline::factory()->withDefaultStages()->create(['tenant_id' => $this->tenant->id]);

    app(CreateDeal::class)->handle(['title' => 'Acme renewal', 'value' => 25000], $pipeline);

    expect(emitted())->toBe(['deal.created']);
});

it('emits deal.won only on reaching a winning stage', function (): void {
    $pipeline = Pipeline::factory()->withDefaultStages()->create(['tenant_id' => $this->tenant->id]);
    $stages = $pipeline->stages()->get()->keyBy('key');

    $deal = app(CreateDeal::class)->handle(['title' => 'Acme', 'value' => 10000], $pipeline);

    app(MoveDealToStage::class)->handle($deal, $stages['qualified']);
    app(MoveDealToStage::class)->handle($deal, $stages['won']);

    // Every intermediate move is visible on the board already; a subscriber
    // told about all of them would filter out almost everything it received.
    expect(emitted())->toBe(['deal.created', 'deal.won']);
});

it('emits deal.lost on reaching a losing stage', function (): void {
    $pipeline = Pipeline::factory()->withDefaultStages()->create(['tenant_id' => $this->tenant->id]);
    $stages = $pipeline->stages()->get()->keyBy('key');

    $deal = app(CreateDeal::class)->handle(['title' => 'Acme', 'value' => 10000], $pipeline);
    app(MoveDealToStage::class)->handle($deal, $stages['lost']);

    expect(emitted())->toBe(['deal.created', 'deal.lost']);
});

// --- Isolation --------------------------------------------------------------

it('never delivers one workspace\'s event to another\'s endpoint', function (): void {
    $other = Tenant::factory()->create();

    app(TenantContext::class)->runAs($other, function () use ($other): void {
        WebhookEndpoint::factory()
            ->subscribedTo([WebhookEvent::ContactCreated])
            ->create(['tenant_id' => $other->id]);
    });

    Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(WebhookDelivery::count())->toBe(1);
});
