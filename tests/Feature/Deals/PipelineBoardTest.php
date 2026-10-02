<?php

declare(strict_types=1);

use App\Domain\Deals\Actions\CreateDeal;
use App\Domain\Deals\Actions\MoveDealToStage;
use App\Domain\Deals\Enums\DealStatus;
use App\Domain\Deals\Events\DealStageChanged;
use App\Domain\Deals\Exceptions\StageTransitionException;
use App\Domain\Tenancy\TenantContext;
use App\Models\Deal;
use App\Models\DealStageChange;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Tenant;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| Pipeline board
|--------------------------------------------------------------------------
|
| Stage movement and card ordering (§22). Ordering gets disproportionate
| attention here because a card that visibly lands in one place and reloads
| into another destroys trust in a Kanban board faster than any other bug.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);

    $this->pipeline = Pipeline::factory()
        ->withDefaultStages()
        ->create(['tenant_id' => $this->tenant->id]);

    $this->stages = $this->pipeline->stages()->get()->keyBy('key');
    $this->create = app(CreateDeal::class);
    $this->move = app(MoveDealToStage::class);
});

function stage(string $key): PipelineStage
{
    return test()->stages[$key];
}

function openDeal(array $attributes = []): Deal
{
    return test()->create->handle([
        'title' => 'Acme renewal',
        'value' => 25000,
        ...$attributes,
    ]);
}

// --- Creation ---------------------------------------------------------------

it('opens a deal in the first stage with that stage probability', function (): void {
    $deal = openDeal();

    expect($deal->pipeline_stage_id)->toBe(stage('new')->id)
        ->and($deal->probability)->toBe(10)
        ->and($deal->status)->toBe(DealStatus::Open);
});

it('puts a new deal at the top of its stage', function (): void {
    $first = openDeal(['title' => 'First']);
    $second = openDeal(['title' => 'Second']);

    // Newest first: an opportunity just opened is the one most likely to need
    // attention, and burying it under older cards is the opposite of useful.
    expect($second->position)->toBe(0)
        ->and($first->refresh()->position)->toBe(1);
});

it('records the opening move so stage duration starts from a real entry', function (): void {
    $deal = openDeal();

    $opening = DealStageChange::where('deal_id', $deal->id)->firstOrFail();

    expect($opening->from_stage_id)->toBeNull()
        ->and($opening->to_stage_id)->toBe(stage('new')->id);
});

// --- Stage movement ---------------------------------------------------------

it('moves a deal and takes the new stage probability', function (): void {
    Event::fake([DealStageChanged::class]);

    $deal = openDeal();
    $moved = $this->move->handle($deal, stage('qualified'));

    expect($moved->pipeline_stage_id)->toBe(stage('qualified')->id)
        ->and($moved->probability)->toBe(40);

    Event::assertDispatched(DealStageChanged::class);
});

it('closes a deal that reaches a winning stage', function (): void {
    $deal = $this->move->handle(openDeal(), stage('won'));

    expect($deal->status)->toBe(DealStatus::Won)
        ->and($deal->probability)->toBe(100)
        ->and($deal->closed_at)->not->toBeNull();
});

it('closes a deal that reaches a losing stage', function (): void {
    $deal = $this->move->handle(openDeal(), stage('lost'));

    expect($deal->status)->toBe(DealStatus::Lost)
        ->and($deal->probability)->toBe(0)
        ->and($deal->closed_at)->not->toBeNull();
});

it('reopens a closed deal dragged back onto the board', function (): void {
    $deal = $this->move->handle(openDeal(), stage('lost'));
    $deal = $this->move->handle($deal, stage('negotiation'));

    // Correcting a mistaken drop must actually undo it.
    expect($deal->status)->toBe(DealStatus::Open)
        ->and($deal->closed_at)->toBeNull()
        ->and($deal->lost_reason)->toBeNull();
});

it('records how long the deal sat in the stage it left', function (): void {
    $deal = openDeal();

    $this->travel(2)->hours();
    $this->move->handle($deal, stage('contacted'));

    $change = DealStageChange::where('deal_id', $deal->id)
        ->whereNotNull('from_stage_id')
        ->firstOrFail();

    expect($change->seconds_in_previous_stage)->toBeGreaterThanOrEqual(7200);
});

it('does not log a stage change when only the position moved', function (): void {
    $deal = openDeal();
    openDeal(['title' => 'Other']);

    $before = DealStageChange::where('deal_id', $deal->id)->count();
    $this->move->handle($deal, stage('new'), position: 1);

    // Reordering within a column is not a pipeline event, and logging it
    // would pollute every stage-duration report.
    expect(DealStageChange::where('deal_id', $deal->id)->count())->toBe($before);
});

// --- Stage rules (§22) ------------------------------------------------------

it('refuses a stage whose required fields are not filled', function (): void {
    $deal = openDeal(['value' => 0]);

    $this->move->handle($deal, stage('proposal'));
})->throws(StageTransitionException::class);

it('leaves the deal untouched when a move is refused', function (): void {
    $deal = openDeal(['value' => 0]);

    try {
        $this->move->handle($deal, stage('proposal'));
    } catch (StageTransitionException $e) {
        expect($e->missingFields)->toBe(['value']);
    }

    // A rejected drop must not half-apply.
    expect($deal->refresh()->pipeline_stage_id)->toBe(stage('new')->id);
});

it('allows the stage once the required field is filled', function (): void {
    $deal = openDeal(['value' => 0]);
    $deal->update(['value' => 50000]);

    expect($this->move->handle($deal, stage('proposal'))->pipeline_stage_id)
        ->toBe(stage('proposal')->id);
});

it('refuses a stage from another pipeline', function (): void {
    $other = Pipeline::factory()
        ->withDefaultStages()
        ->create(['tenant_id' => $this->tenant->id, 'is_default' => false]);

    $this->move->handle(openDeal(), $other->stages()->first());
})->throws(StageTransitionException::class);

// --- Card ordering ----------------------------------------------------------

it('drops a card at the exact index requested', function (): void {
    // Created newest-first, so the column reads C, B, A.
    $a = openDeal(['title' => 'A']);
    $b = openDeal(['title' => 'B']);
    $c = openDeal(['title' => 'C']);

    // Drag A into the middle.
    $this->move->handle($a, stage('new'), position: 1);

    $order = Deal::where('pipeline_stage_id', stage('new')->id)
        ->boardOrder()
        ->pluck('title')
        ->all();

    expect($order)->toBe(['C', 'A', 'B'])
        ->and($b->refresh()->position)->toBe(2)
        ->and($c->refresh()->position)->toBe(0);
});

it('renumbers positions contiguously from zero', function (): void {
    foreach (range(1, 4) as $i) {
        openDeal(['title' => "Deal {$i}"]);
    }

    $deal = Deal::where('title', 'Deal 1')->firstOrFail();
    $this->move->handle($deal, stage('qualified'));

    // Gaps would eventually collide once an index is reused.
    expect(Deal::where('pipeline_stage_id', stage('new')->id)->boardOrder()->pluck('position')->all())
        ->toBe([0, 1, 2]);
});

it('appends to the end when no position is given', function (): void {
    openDeal(['title' => 'Already there']);
    $moved = openDeal(['title' => 'Moving']);

    $this->move->handle($moved, stage('qualified'));
    $second = openDeal(['title' => 'Second in qualified']);
    $this->move->handle($second, stage('qualified'));

    expect(Deal::where('pipeline_stage_id', stage('qualified')->id)->boardOrder()->pluck('title')->all())
        ->toBe(['Moving', 'Second in qualified']);
});

it('clamps a position beyond the end of the column', function (): void {
    $a = openDeal(['title' => 'A']);
    openDeal(['title' => 'B']);

    // A board that has drifted out of sync must not produce a gap.
    $this->move->handle($a, stage('new'), position: 99);

    expect(Deal::where('pipeline_stage_id', stage('new')->id)->boardOrder()->pluck('title')->all())
        ->toBe(['B', 'A']);
});

it('keeps the two columns independent when a card moves between them', function (): void {
    $a = openDeal(['title' => 'A']);
    $b = openDeal(['title' => 'B']);
    $c = openDeal(['title' => 'C']);

    $this->move->handle($b, stage('contacted'));

    expect(Deal::where('pipeline_stage_id', stage('new')->id)->boardOrder()->pluck('title')->all())
        ->toBe(['C', 'A'])
        ->and(Deal::where('pipeline_stage_id', stage('contacted')->id)->boardOrder()->pluck('title')->all())
        ->toBe(['B'])
        ->and($c->refresh()->position)->toBe(0)
        ->and($a->refresh()->position)->toBe(1);
});

// --- Forecasting ------------------------------------------------------------

it('weights deal value by stage probability', function (): void {
    $deal = $this->move->handle(openDeal(['value' => 10000]), stage('negotiation'));

    expect($deal->weightedValue())->toBe(8500.0);
});

// --- Isolation --------------------------------------------------------------

it('never shows another workspace a deal', function (): void {
    openDeal(['title' => 'Ours']);

    $other = Tenant::factory()->create();

    app(TenantContext::class)->runAs($other, function (): void {
        expect(Deal::count())->toBe(0);
    });
});
