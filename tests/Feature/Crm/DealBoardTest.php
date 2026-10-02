<?php

declare(strict_types=1);

use App\Domain\Deals\Actions\CreateDeal;
use App\Domain\Deals\Enums\DealStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Deal board
|--------------------------------------------------------------------------
|
| The Kanban endpoint a drop posts to (§22).
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

    foreach (['deal.view', 'deal.update'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $this->editor = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->editor->assignRole(
        Role::findOrCreate('Sales', 'web')->syncPermissions(['deal.view', 'deal.update']),
    );

    $this->viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->viewer->assignRole(
        Role::findOrCreate('Read only', 'web')->syncPermissions(['deal.view']),
    );

    $this->pipeline = Pipeline::factory()
        ->withDefaultStages()
        ->create(['tenant_id' => $this->tenant->id]);

    $this->stages = $this->pipeline->stages()->get()->keyBy('key');
});

function boardProps(): array
{
    return test()->actingAs(test()->editor)
        ->get('/deals')
        ->assertOk()
        ->viewData('page')['props'];
}

function makeDeal(array $attributes = []): Deal
{
    return app(CreateDeal::class)->handle(
        ['title' => 'Acme renewal', 'value' => 25000, ...$attributes],
        test()->pipeline,
    );
}

// --- Board ------------------------------------------------------------------

it('renders every stage as a column', function (): void {
    expect(boardProps()['stages'])->toHaveCount(8);
});

it('reports the true total even when the column is truncated', function (): void {
    makeDeal(['title' => 'One']);
    makeDeal(['title' => 'Two']);

    $first = collect(boardProps()['stages'])->firstWhere('key', 'new');

    // The header count must reflect the database, not how many cards were
    // sent: a column capped at fifty still has to say it holds four hundred.
    expect($first['total'])->toBe(2)
        ->and($first['cards'])->toHaveCount(2);
});

it('rolls up the value of each column', function (): void {
    makeDeal(['value' => 10000]);
    makeDeal(['value' => 5000]);

    expect(collect(boardProps()['stages'])->firstWhere('key', 'new')['value'])
        ->toBe(15000.0);
});

it('requires deal.view to open the board', function (): void {
    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($stranger)->get('/deals')->assertForbidden();
});

// --- Moving -----------------------------------------------------------------

it('moves a card to another stage', function (): void {
    $deal = makeDeal();

    $this->actingAs($this->editor)
        ->post("/deals/{$deal->id}/move", [
            'stage_id' => $this->stages['qualified']->id,
            'position' => 0,
        ])
        ->assertRedirect();

    expect($deal->refresh()->pipeline_stage_id)->toBe($this->stages['qualified']->id)
        ->and($deal->probability)->toBe(40);
});

it('closes a deal dropped on a winning stage', function (): void {
    $deal = makeDeal();

    $this->actingAs($this->editor)
        ->post("/deals/{$deal->id}/move", ['stage_id' => $this->stages['won']->id]);

    expect($deal->refresh()->status)->toBe(DealStatus::Won)
        ->and($deal->closed_at)->not->toBeNull();
});

it('reorders within a stage without changing it', function (): void {
    $a = makeDeal(['title' => 'A']);
    $b = makeDeal(['title' => 'B']);

    // Created newest-first, so the column reads B, A. Send A to the top.
    $this->actingAs($this->editor)
        ->post("/deals/{$a->id}/move", [
            'stage_id' => $this->stages['new']->id,
            'position' => 0,
        ]);

    expect(Deal::where('pipeline_stage_id', $this->stages['new']->id)->boardOrder()->pluck('title')->all())
        ->toBe(['A', 'B'])
        ->and($b->refresh()->pipeline_stage_id)->toBe($this->stages['new']->id);
});

it('binds the route model after the tenant has been resolved', function (): void {
    $deal = makeDeal();

    // Clearing the context forces the request to bind the tenant itself,
    // which is what a real browser request does.
    //
    // Route model binding looks {deal} up through the tenant-scoped model, so
    // if ResolveTenant runs after SubstituteBindings the scope fails closed
    // and every bound route 404s. The other tests here cannot catch that
    // because they leave a tenant bound in the container beforehand.
    app(TenantContext::class)->forget();

    $this->actingAs($this->editor)
        ->post("/deals/{$deal->id}/move", ['stage_id' => $this->stages['qualified']->id])
        ->assertRedirect();

    expect($deal->refresh()->pipeline_stage_id)->toBe($this->stages['qualified']->id);
});

it('explains which field blocked a move', function (): void {
    $deal = makeDeal(['value' => 0]);

    $response = $this->actingAs($this->editor)
        ->from('/deals')
        ->post("/deals/{$deal->id}/move", ['stage_id' => $this->stages['proposal']->id]);

    // A generic failure leaves the user with nowhere to go (§59), so the
    // refusal names the field.
    $response->assertSessionHasErrors('stage_id');
    expect(session('errors')->first('stage_id'))->toContain('value');

    // And the card must not have moved.
    expect($deal->refresh()->pipeline_stage_id)->toBe($this->stages['new']->id);
});

it('requires deal.update to move a card', function (): void {
    $deal = makeDeal();

    $this->actingAs($this->viewer)
        ->post("/deals/{$deal->id}/move", ['stage_id' => $this->stages['qualified']->id])
        ->assertForbidden();

    expect($deal->refresh()->pipeline_stage_id)->toBe($this->stages['new']->id);
});

it('validates the move payload', function (): void {
    $deal = makeDeal();

    $this->actingAs($this->editor)
        ->from('/deals')
        ->post("/deals/{$deal->id}/move", [])
        ->assertSessionHasErrors('stage_id');

    $this->actingAs($this->editor)
        ->from('/deals')
        ->post("/deals/{$deal->id}/move", [
            'stage_id' => $this->stages['new']->id,
            'position' => -1,
        ])
        ->assertSessionHasErrors('position');
});

// --- Isolation --------------------------------------------------------------

it('cannot move a deal into another workspace stage', function (): void {
    $deal = makeDeal();

    $other = Tenant::factory()->create();
    $foreignStage = app(TenantContext::class)->runAs($other, function () use ($other) {
        return Pipeline::factory()
            ->withDefaultStages()
            ->create(['tenant_id' => $other->id])
            ->stages()
            ->first();
    });

    // The stage is looked up through the tenant-scoped model, so an id from
    // another workspace simply does not resolve.
    $this->actingAs($this->editor)
        ->post("/deals/{$deal->id}/move", ['stage_id' => $foreignStage->id])
        ->assertNotFound();

    expect($deal->refresh()->pipeline_stage_id)->toBe($this->stages['new']->id);
});

it('cannot move another workspace deal', function (): void {
    $other = Tenant::factory()->create();

    $foreignDeal = app(TenantContext::class)->runAs($other, function () use ($other) {
        $pipeline = Pipeline::factory()->withDefaultStages()->create(['tenant_id' => $other->id]);

        return app(CreateDeal::class)->handle(['title' => 'Theirs', 'value' => 1], $pipeline);
    });

    $this->actingAs($this->editor)
        ->post("/deals/{$foreignDeal->id}/move", ['stage_id' => $this->stages['qualified']->id])
        ->assertNotFound();
});
