<?php

declare(strict_types=1);

use App\Domain\Deals\Actions\CreateDeal;
use App\Domain\Deals\Actions\MoveDealToStage;
use App\Domain\Deals\Enums\DealStatus;
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
| The deal detail screen
|--------------------------------------------------------------------------
|
| §22, §44. Overview, who the deal is with, stage history with time spent in
| each stage, and audit.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    Queue::fake();

    $this->seed(PermissionSeeder::class);

    $role = Role::findOrCreate('Rep', 'web');
    $role->syncPermissions(['deal.view', 'deal.update', 'audit.view']);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);

    $this->pipeline = Pipeline::factory()
        ->withDefaultStages()
        ->create(['tenant_id' => $this->tenant->id]);

    $this->stages = $this->pipeline->stages()->get()->keyBy('key');
});

function dealUnderTest(array $attributes = []): Deal
{
    return app(CreateDeal::class)->handle(
        ['title' => 'Northwind renewal', 'value' => 25000, 'currency' => 'AED', ...$attributes],
        test()->pipeline,
    );
}

function dealProps(Deal $deal): array
{
    return test()->actingAs(test()->user)
        ->get("/deals/{$deal->id}")
        ->assertOk()
        ->viewData('page')['props'];
}

// --- Access ---------------------------------------------------------------------

it('requires the deal.view permission', function (): void {
    $deal = dealUnderTest();
    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($stranger)->get("/deals/{$deal->id}")->assertForbidden();
});

it('cannot open a deal from another workspace', function (): void {
    $other = Tenant::factory()->create();

    $foreign = app(TenantContext::class)->runAs($other, function () use ($other): Deal {
        $pipeline = Pipeline::factory()->withDefaultStages()->create(['tenant_id' => $other->id]);

        return Deal::factory()->create([
            'tenant_id' => $other->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $pipeline->firstStage()->id,
        ]);
    });

    $this->actingAs($this->user)->get("/deals/{$foreign->id}")->assertNotFound();
});

// --- Overview -------------------------------------------------------------------

it('shows the value and the weighted value', function (): void {
    $deal = dealUnderTest();

    $props = dealProps($deal);

    expect($props['deal']['value'])->toBe(25000.0)
        ->and($props['deal']['probability'])->toBe(10)
        // Computed once on the server so every reader agrees about the
        // rounding rather than each recomputing it.
        ->and($props['deal']['weighted_value'])->toBe(2500.0)
        ->and($props['deal']['currency'])->toBe('AED')
        ->and($props['deal']['status'])->toBe(DealStatus::Open->value);
});

it('shows the stage and pipeline it is on', function (): void {
    $props = dealProps(dealUnderTest());

    expect($props['deal']['stage'])->toBe('New')
        ->and($props['deal']['pipeline'])->toBe($this->pipeline->name)
        ->and($props['deal']['is_open'])->toBeTrue();
});

// --- Who it is with -------------------------------------------------------------

it('shows the company and the main contact', function (): void {
    $company = Company::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Northwind Trading',
    ]);
    $contact = Contact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Amara',
        'last_name' => 'Okafor',
    ]);

    $deal = dealUnderTest(['company_id' => $company->id, 'contact_id' => $contact->id]);

    $props = dealProps($deal);

    expect($props['related']['company']['name'])->toBe('Northwind Trading')
        ->and($props['related']['contact']['name'])->toBe('Amara Okafor')
        ->and($props['related']['contact']['email'])->toBe($contact->email);
});

it('shows the lead the deal came from', function (): void {
    $lead = Lead::factory()->scored(70)->create(['tenant_id' => $this->tenant->id]);

    $props = dealProps(dealUnderTest(['lead_id' => $lead->id]));

    // §85. The acquisition story has to survive the conversion to an
    // opportunity, or attribution stops at the deal.
    expect($props['related']['lead']['id'])->toBe($lead->id)
        ->and($props['related']['lead']['score'])->toBe(70);
});

it('reports nothing linked rather than inventing a relationship', function (): void {
    $props = dealProps(dealUnderTest());

    expect($props['related']['company'])->toBeNull()
        ->and($props['related']['contact'])->toBeNull()
        ->and($props['related']['lead'])->toBeNull();
});

// --- Stage history --------------------------------------------------------------

it('records the opening move so the history has a start', function (): void {
    $props = dealProps(dealUnderTest());

    expect($props['stageHistory'])->toHaveCount(1)
        ->and($props['stageHistory'][0]['from'])->toBeNull()
        ->and($props['stageHistory'][0]['to'])->toBe('New');
});

it('lists the moves oldest first, because it reads as a story', function (): void {
    $deal = dealUnderTest();

    app(MoveDealToStage::class)->handle($deal, $this->stages['qualified']);
    app(MoveDealToStage::class)->handle($deal, $this->stages['negotiation']);

    $props = dealProps($deal);

    expect(array_column($props['stageHistory'], 'to'))
        ->toBe(['New', 'Qualified', 'Negotiation']);
});

it('shows how long the deal sat in the previous stage', function (): void {
    $deal = dealUnderTest();

    $this->travel(3)->days();
    app(MoveDealToStage::class)->handle($deal, $this->stages['qualified']);

    $move = collect(dealProps($deal)['stageHistory'])->firstWhere('to', 'Qualified');

    // Recorded on the move rather than derived here, so renaming or deleting a
    // stage later cannot rewrite history (§22).
    expect($move['seconds_in_previous'])->toBeGreaterThan(86000)
        ->and($move['from'])->toBe('New');
});

it('shows a closed deal as closed, with the reason it was lost', function (): void {
    $deal = dealUnderTest();

    app(MoveDealToStage::class)->handle($deal, $this->stages['lost']);
    $deal->forceFill(['lost_reason' => 'Chose a competitor on price'])->save();

    $props = dealProps($deal->refresh());

    expect($props['deal']['status'])->toBe(DealStatus::Lost->value)
        ->and($props['deal']['is_open'])->toBeFalse()
        ->and($props['deal']['closed_at'])->not->toBeNull()
        ->and($props['deal']['lost_reason'])->toBe('Chose a competitor on price');
});

// --- Audit ----------------------------------------------------------------------

it('defers the audit trail until it is asked for', function (): void {
    $deal = dealUnderTest();

    expect(dealProps($deal))->not->toHaveKey('audit');

    $version = $this->actingAs($this->user)
        ->get("/deals/{$deal->id}")
        ->viewData('page')['version'];

    $props = $this->actingAs($this->user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'crm/deals/Show',
            'X-Inertia-Partial-Data' => 'audit',
        ])
        ->get("/deals/{$deal->id}")
        ->assertOk()
        ->json('props');

    expect($props['audit'])->toBeArray()->not->toBeEmpty();
});

it('shows only this deal changes in its audit tab', function (): void {
    $deal = dealUnderTest();
    dealUnderTest(['title' => 'Someone else']);

    $version = $this->actingAs($this->user)
        ->get("/deals/{$deal->id}")
        ->viewData('page')['version'];

    $props = $this->actingAs($this->user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'crm/deals/Show',
            'X-Inertia-Partial-Data' => 'audit',
        ])
        ->get("/deals/{$deal->id}")
        ->json('props');

    expect($props['audit'])->toHaveCount(1)
        ->and($props['audit'][0]['action'])->toBe('deal.created');
});

// --- Routing --------------------------------------------------------------------

it('does not swallow the move route', function (): void {
    $deal = dealUnderTest();

    // `/deals/{deal}` is declared before the write routes, so a GET resolves to
    // the detail page while POST /deals/{deal}/move still reaches the board.
    $this->actingAs($this->user)
        ->post("/deals/{$deal->id}/move", ['stage_id' => $this->stages['qualified']->id])
        ->assertRedirect();

    expect($deal->refresh()->pipeline_stage_id)->toBe($this->stages['qualified']->id);
});
