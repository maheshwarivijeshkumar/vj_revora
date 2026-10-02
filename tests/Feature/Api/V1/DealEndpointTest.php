<?php

declare(strict_types=1);

use App\Domain\Deals\Actions\CreateDeal;
use App\Domain\Tenancy\TenantContext;
use App\Models\ApiKey;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Tenant;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| Deal endpoints
|--------------------------------------------------------------------------
|
| §47. Stage movement runs through the same action the Kanban board uses, so
| the pipeline's rules apply identically to an integration (§22).
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);

    $this->pipeline = Pipeline::factory()
        ->withDefaultStages()
        ->create(['tenant_id' => $this->tenant->id]);

    $this->stages = $this->pipeline->stages()->get()->keyBy('key');
    $this->token = ApiKey::mint('Test', ['deals.write'])['token'];
});

function dealApi(string $method, string $path, array $body = []): TestResponse
{
    return test()
        ->withHeader('Authorization', 'Bearer '.test()->token)
        ->json($method, $path, $body);
}

function seedDeal(array $attributes = []): Deal
{
    return app(CreateDeal::class)->handle(
        ['title' => 'Acme renewal', 'value' => 25000, ...$attributes],
        test()->pipeline,
    );
}

// --- Creating ---------------------------------------------------------------

it('opens a deal in the first stage', function (): void {
    $response = dealApi('POST', '/api/v1/deals', [
        'title' => 'Northwind licence',
        'value' => 48000,
    ])->assertCreated();

    expect($response->json('data.title'))->toBe('Northwind licence')
        ->and($response->json('data.stage.key'))->toBe('new')
        ->and($response->json('data.probability'))->toBe(10)
        ->and($response->json('data.status'))->toBe('open');
});

it('returns the weighted value so callers do not recompute it', function (): void {
    $deal = seedDeal(['value' => 10000]);

    $response = dealApi('POST', "/api/v1/deals/{$deal->uuid}/move", [
        'stage_id' => $this->stages['negotiation']->id,
    ])->assertOk();

    // Cast because JSON serialises a whole float without a decimal point, so
    // the decoded value arrives as an int. Every client parses it as a
    // number either way.
    expect((float) $response->json('data.weighted_value'))->toBe(8500.0);
});

it('rejects a pipeline belonging to another workspace', function (): void {
    $other = Tenant::factory()->create();

    $foreign = app(TenantContext::class)->runAs($other, fn () => Pipeline::factory()
        ->withDefaultStages()
        ->create(['tenant_id' => $other->id]));

    dealApi('POST', '/api/v1/deals', [
        'title' => 'Cross-tenant',
        'pipeline_id' => $foreign->id,
    ])->assertNotFound();
});

// --- Moving -----------------------------------------------------------------

it('moves a deal and applies the stage probability', function (): void {
    $deal = seedDeal();

    $response = dealApi('POST', "/api/v1/deals/{$deal->uuid}/move", [
        'stage_id' => $this->stages['qualified']->id,
    ])->assertOk();

    expect($response->json('data.stage.key'))->toBe('qualified')
        ->and($response->json('data.probability'))->toBe(40);
});

it('closes a deal moved to a winning stage', function (): void {
    $deal = seedDeal();

    $response = dealApi('POST', "/api/v1/deals/{$deal->uuid}/move", [
        'stage_id' => $this->stages['won']->id,
    ])->assertOk();

    expect($response->json('data.status'))->toBe('won')
        ->and($response->json('data.closed_at'))->not->toBeNull();
});

it('returns 422 and names the field when a stage refuses the move', function (): void {
    $deal = seedDeal(['value' => 0]);

    $response = dealApi('POST', "/api/v1/deals/{$deal->uuid}/move", [
        'stage_id' => $this->stages['proposal']->id,
    ])->assertStatus(422);

    // The request was well-formed; the workspace's own stage rules refused it.
    expect($response->json('missing_fields'))->toBe(['value'])
        ->and($deal->refresh()->pipeline_stage_id)->toBe($this->stages['new']->id);
});

it('refuses a stage from another workspace', function (): void {
    $deal = seedDeal();
    $other = Tenant::factory()->create();

    $foreignStage = app(TenantContext::class)->runAs($other, fn () => Pipeline::factory()
        ->withDefaultStages()
        ->create(['tenant_id' => $other->id])
        ->stages()
        ->first());

    dealApi('POST', "/api/v1/deals/{$deal->uuid}/move", ['stage_id' => $foreignStage->id])
        ->assertNotFound();
});

// --- Updating ---------------------------------------------------------------

it('updates editable fields', function (): void {
    $deal = seedDeal();

    $response = dealApi('PATCH', "/api/v1/deals/{$deal->uuid}", [
        'title' => 'Renamed',
        'value' => 99000,
    ])->assertOk()->assertJsonPath('data.title', 'Renamed');

    expect((float) $response->json('data.value'))->toBe(99000.0);
});

it('refuses a stage change through update and says where to go', function (): void {
    $deal = seedDeal();

    $response = dealApi('PATCH', "/api/v1/deals/{$deal->uuid}", [
        'stage_id' => $this->stages['won']->id,
    ])->assertUnprocessable();

    // A move recalculates probability, may close the deal and reorders two
    // columns. Allowing it as a field assignment would bypass all of that.
    expect($response->json('errors.stage_id.0'))->toContain('/move');
});

it('bounds probability to a percentage', function (): void {
    $deal = seedDeal();

    // Out of range would quietly corrupt the weighted forecast.
    dealApi('PATCH', "/api/v1/deals/{$deal->uuid}", ['probability' => 150])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('probability');
});

// --- Listing ----------------------------------------------------------------

it('lists deals with their stage and pipeline', function (): void {
    seedDeal();

    $response = dealApi('GET', '/api/v1/deals')->assertOk();

    expect($response->json('meta.total'))->toBe(1)
        ->and($response->json('data.0.stage.name'))->toBe('New')
        ->and($response->json('data.0.pipeline.name'))->toBe($this->pipeline->name);
});

it('exposes pipelines and their stages', function (): void {
    $response = dealApi('GET', '/api/v1/pipelines')->assertOk();

    expect($response->json('data.0.stages'))->toHaveCount(8);

    $proposal = collect($response->json('data.0.stages'))->firstWhere('key', 'proposal');

    // So a caller can see which fields a stage will demand before trying.
    expect($proposal['required_fields'])->toBe(['value']);
});

// --- Deleting ---------------------------------------------------------------

it('soft deletes a deal', function (): void {
    $deal = seedDeal();

    dealApi('DELETE', "/api/v1/deals/{$deal->uuid}")->assertNoContent();

    expect(Deal::count())->toBe(0)
        ->and(Deal::withTrashed()->count())->toBe(1);
});
