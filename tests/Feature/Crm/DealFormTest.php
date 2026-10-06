<?php

declare(strict_types=1);

use App\Domain\Deals\Enums\DealStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\DealStageChange;
use App\Models\Pipeline;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Creating and editing a deal from the UI
|--------------------------------------------------------------------------
|
| §22. A deal opens through the same action the API uses, and the stage is not
| an editable field: moving it is the board's job.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    Queue::fake();

    $this->seed(PermissionSeeder::class);

    $role = Role::findOrCreate('Rep', 'web');
    $role->syncPermissions(['deal.view', 'deal.create', 'deal.update', 'deal.delete']);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);

    $this->pipeline = Pipeline::factory()
        ->withDefaultStages()
        ->create(['tenant_id' => $this->tenant->id]);
});

function postDeal(array $overrides = []): TestResponse
{
    return test()->actingAs(test()->user)
        ->from('/deals')
        ->post('/deals', [
            'title' => 'Northwind renewal',
            'value' => 25000,
            'currency' => 'AED',
            'pipeline_id' => test()->pipeline->id,
            ...$overrides,
        ]);
}

// --- Access ---------------------------------------------------------------------

it('gates create, edit and delete separately', function (): void {
    $viewer = Role::findOrCreate('Viewer', 'web');
    $viewer->syncPermissions(['deal.view']);

    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole($viewer);

    $deal = Deal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $this->pipeline->firstStage()->id,
    ]);

    $this->actingAs($user)->post('/deals', ['title' => 'Nope'])->assertForbidden();
    $this->actingAs($user)->patch("/deals/{$deal->id}", ['title' => 'Nope'])
        ->assertForbidden();
    $this->actingAs($user)->delete("/deals/{$deal->id}")->assertForbidden();
});

// --- Creating -------------------------------------------------------------------

it('opens a deal in the first stage with that stage probability', function (): void {
    postDeal()->assertSessionHas('success');

    $deal = Deal::sole();

    expect($deal->title)->toBe('Northwind renewal')
        ->and($deal->stage->key)->toBe('new')
        ->and($deal->probability)->toBe(10)
        ->and($deal->status)->toBe(DealStatus::Open)
        // Top of the column: a newly opened opportunity is the one most likely
        // to need attention.
        ->and($deal->position)->toBe(0);
});

it('writes the opening stage change so duration reporting has a start', function (): void {
    postDeal();

    $change = DealStageChange::sole();

    expect($change->from_stage_id)->toBeNull()
        ->and($change->to_stage_id)->toBe($this->pipeline->firstStage()->id);
});

it('treats a blank value as not known yet rather than refusing to save', function (): void {
    postDeal(['value' => ''])->assertSessionHas('success');

    // A deal often exists before its number does, and refusing it would push
    // people to type a fake one.
    expect((float) Deal::sole()->value)->toBe(0.0);
});

it('links the company and the contact', function (): void {
    $company = Company::factory()->create(['tenant_id' => $this->tenant->id]);
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    postDeal(['company_id' => $company->id, 'contact_id' => $contact->id]);

    expect(Deal::sole()->company_id)->toBe($company->id)
        ->and(Deal::sole()->contact_id)->toBe($contact->id);
});

it('honours an explicit probability over the stage default', function (): void {
    postDeal(['probability' => 75]);

    expect(Deal::sole()->probability)->toBe(75);
});

// --- Validation -----------------------------------------------------------------

it('requires a title, a currency and a pipeline', function (): void {
    postDeal(['title' => ''])->assertSessionHasErrors('title');
    postDeal(['currency' => ''])->assertSessionHasErrors('currency');
    postDeal(['pipeline_id' => ''])->assertSessionHasErrors('pipeline_id');

    expect(Deal::count())->toBe(0);
});

it('refuses a negative value', function (): void {
    // A negative deal is a refund, which this is not.
    postDeal(['value' => -500])->assertSessionHasErrors('value');
});

it('bounds probability to a percentage', function (): void {
    postDeal(['probability' => 150])->assertSessionHasErrors('probability');
    postDeal(['probability' => -1])->assertSessionHasErrors('probability');
});

it('validates the currency code length', function (): void {
    postDeal(['currency' => 'Dollars'])->assertSessionHasErrors('currency');
});

it('refuses a pipeline, owner, company or contact from another workspace', function (): void {
    $other = Tenant::factory()->create();

    $stranger = User::factory()->create(['tenant_id' => $other->id]);

    [$foreignPipeline, $foreignCompany, $foreignContact] = app(TenantContext::class)
        ->runAs($other, fn (): array => [
            Pipeline::factory()->withDefaultStages()->create(['tenant_id' => $other->id]),
            Company::factory()->create(['tenant_id' => $other->id]),
            Contact::factory()->create(['tenant_id' => $other->id]),
        ]);

    // `exists` queries the table directly, so the global scope never runs and
    // each of these has to be checked explicitly (ADR-009).
    postDeal(['pipeline_id' => $foreignPipeline->id])->assertSessionHasErrors('pipeline_id');
    postDeal(['owner_id' => $stranger->id])->assertSessionHasErrors('owner_id');
    postDeal(['company_id' => $foreignCompany->id])->assertSessionHasErrors('company_id');
    postDeal(['contact_id' => $foreignContact->id])->assertSessionHasErrors('contact_id');

    expect(Deal::count())->toBe(0);
});

// --- Editing ---------------------------------------------------------------------

it('updates the fields the form offers', function (): void {
    postDeal();
    $deal = Deal::sole();

    $this->actingAs($this->user)
        ->from('/deals')
        ->patch("/deals/{$deal->id}", [
            'title' => 'Renamed renewal',
            'value' => 48000,
            'currency' => 'USD',
            'pipeline_id' => $this->pipeline->id,
            'expected_close_date' => '2027-03-31',
        ])
        ->assertSessionHas('success');

    $deal->refresh();

    expect($deal->title)->toBe('Renamed renewal')
        ->and((float) $deal->value)->toBe(48000.0)
        ->and($deal->currency)->toBe('USD')
        ->and($deal->expected_close_date->toDateString())->toBe('2027-03-31');
});

it('does not let an edit change the stage', function (): void {
    postDeal();
    $deal = Deal::sole();
    $stages = $this->pipeline->stages()->get()->keyBy('key');

    $this->actingAs($this->user)->patch("/deals/{$deal->id}", [
        'title' => $deal->title,
        'value' => $deal->value,
        'currency' => $deal->currency,
        'pipeline_id' => $this->pipeline->id,
        'pipeline_stage_id' => $stages['won']->id,
    ]);

    // A move recalculates probability, may close the deal and reorders two
    // columns. The board owns it, so the field is simply not in the ruleset.
    expect($deal->refresh()->pipeline_stage_id)->toBe($stages['new']->id)
        ->and($deal->status)->toBe(DealStatus::Open);
});

it('does not let an edit move the deal to another pipeline', function (): void {
    postDeal();
    $deal = Deal::sole();

    $other = Pipeline::factory()->withDefaultStages()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->user)->patch("/deals/{$deal->id}", [
        'title' => $deal->title,
        'value' => $deal->value,
        'currency' => $deal->currency,
        'pipeline_id' => $other->id,
    ]);

    // It would orphan the deal on a stage belonging to the old pipeline.
    expect($deal->refresh()->pipeline_id)->toBe($this->pipeline->id);
});

it('can clear the owner, company and contact', function (): void {
    $company = Company::factory()->create(['tenant_id' => $this->tenant->id]);
    postDeal(['company_id' => $company->id]);

    $deal = Deal::sole();

    $this->actingAs($this->user)->patch("/deals/{$deal->id}", [
        'title' => $deal->title,
        'value' => $deal->value,
        'currency' => $deal->currency,
        'pipeline_id' => $this->pipeline->id,
    ]);

    // "Nobody" and "no company" have to be things the form can say.
    expect($deal->refresh()->company_id)->toBeNull()
        ->and($deal->owner_id)->toBeNull();
});

it('cannot touch a deal in another workspace', function (): void {
    $other = Tenant::factory()->create();

    $foreign = app(TenantContext::class)->runAs($other, function () use ($other): Deal {
        $pipeline = Pipeline::factory()->withDefaultStages()->create(['tenant_id' => $other->id]);

        return Deal::factory()->create([
            'tenant_id' => $other->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $pipeline->firstStage()->id,
        ]);
    });

    $this->actingAs($this->user)
        ->patch("/deals/{$foreign->id}", ['title' => 'Taken'])
        ->assertNotFound();

    $this->actingAs($this->user)->delete("/deals/{$foreign->id}")->assertNotFound();
});

// --- Deleting ---------------------------------------------------------------------

it('soft deletes so an accidental delete is recoverable', function (): void {
    postDeal();
    $deal = Deal::sole();

    $this->actingAs($this->user)
        ->from('/deals')
        ->delete("/deals/{$deal->id}")
        ->assertSessionHas('success');

    expect(Deal::count())->toBe(0)
        ->and(Deal::withTrashed()->count())->toBe(1);
});

// --- Audited and announced ---------------------------------------------------------

it('audits the creation against the person who did it', function (): void {
    postDeal();

    expect(AuditLog::query()->where('action', 'deal.created')->sole()->actor_id)
        ->toBe($this->user->id);
});
