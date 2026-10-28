<?php

declare(strict_types=1);

use App\Domain\Deals\Actions\MoveDealToStage;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Client;
use App\Models\Deal;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

    foreach ([
        'company.view', 'company.create', 'company.update',
        'quote.view', 'quote.create', 'quote.update',
        'billing.view', 'billing.manage',
    ] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
});

it('turns a won lead into one client and one project', function (): void {
    $lead = Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Nadia',
        'last_name' => 'Haddad',
        'status' => LeadStatus::Qualified,
    ]);

    $lead->status = LeadStatus::Won;
    $lead->save();

    expect(Client::query()->count())->toBe(1)
        ->and(Project::query()->count())->toBe(1)
        ->and(Client::query()->first()->lead_id)->toBe($lead->id)
        ->and($lead->fresh()->converted_at)->not->toBeNull();

    $lead->company_name = 'Ironbridge';
    $lead->save();

    expect(Client::query()->count())->toBe(1)
        ->and(Project::query()->count())->toBe(1);
});

it('refuses to convert a lead that has not been won', function (): void {
    grantOperations(['company.create']);

    $lead = Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => LeadStatus::Qualified,
    ]);

    $this->actingAs($this->user)
        ->from('/leads/'.$lead->id)
        ->post('/leads/'.$lead->id.'/client')
        ->assertRedirect('/leads/'.$lead->id)
        ->assertSessionHas('error');

    expect(Client::query()->count())->toBe(0);
});

it('invoices an accepted estimate for that client', function (): void {
    grantOperations([
        'company.create', 'quote.create', 'quote.update', 'billing.manage',
    ]);

    $lead = Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => LeadStatus::Won,
    ]);

    $this->actingAs($this->user)
        ->post('/leads/'.$lead->id.'/client')
        ->assertRedirect();

    $client = Client::query()->first();

    $this->actingAs($this->user)->post('/estimates', [
        'client_id' => $client->id,
        'title' => 'Website build',
        'tax_rate' => 10,
        'lines' => [
            ['description' => 'Design and build', 'quantity' => 2, 'unit_price' => 100],
        ],
    ])->assertRedirect();

    $estimate = Estimate::query()->first();

    expect($estimate->total)->toBe('220.00')
        ->and($estimate->status->value)->toBe('draft');

    $this->actingAs($this->user)
        ->from('/estimates/'.$estimate->id)
        ->post('/estimates/'.$estimate->id.'/invoice')
        ->assertRedirect()
        ->assertSessionHasErrors('estimate');

    expect(Invoice::query()->count())->toBe(0);

    $this->actingAs($this->user)->post('/estimates/'.$estimate->id.'/accept')->assertRedirect();
    $this->actingAs($this->user)->post('/estimates/'.$estimate->id.'/invoice')->assertRedirect();

    $invoice = Invoice::query()->first();

    expect($invoice->total)->toBe('220.00')
        ->and($invoice->client_id)->toBe($client->id)
        ->and($invoice->estimate_id)->toBe($estimate->id);

    $this->actingAs($this->user)->post('/invoices/'.$invoice->id.'/send')->assertRedirect();
    $this->actingAs($this->user)->post('/invoices/'.$invoice->id.'/pay')->assertRedirect();

    expect($invoice->fresh()->status->value)->toBe('paid')
        ->and($invoice->fresh()->paid_amount)->toBe('220.00');
});

it('hides estimates from someone who cannot view quotes', function (): void {
    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($stranger)->get('/estimates')->assertForbidden();
    $this->actingAs($stranger)->get('/invoices')->assertForbidden();
    $this->actingAs($stranger)->get('/clients')->assertForbidden();
});

it('converts the lead when their deal is won', function (): void {
    $lead = Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => LeadStatus::Proposal,
    ]);

    $pipeline = Pipeline::factory()->withDefaultStages()->create(['tenant_id' => $this->tenant->id]);
    $stages = $pipeline->stages()->get()->keyBy('key');

    $deal = Deal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $stages['qualified']->id,
        'lead_id' => $lead->id,
        'value' => 5000,
    ]);

    app(MoveDealToStage::class)->handle($deal, $stages['won']);

    expect($lead->fresh()->status)->toBe(LeadStatus::Won)
        ->and(Client::query()->where('lead_id', $lead->id)->exists())->toBeTrue();
});

/**
 * @param  list<string>  $permissions
 */
function grantOperations(array $permissions): void
{
    $role = Role::findOrCreate('Operations-'.implode('-', $permissions), 'web');
    $role->syncPermissions($permissions);

    test()->user = User::factory()->create(['tenant_id' => test()->tenant->id]);
    test()->user->assignRole($role);
}
