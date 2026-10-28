<?php

declare(strict_types=1);

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\Tenant;
use App\Models\Tender;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('counts open leads, qualified leads, open deals and open IT tenders', function (): void {
    Lead::factory()->create(['tenant_id' => $this->tenant->id, 'status' => LeadStatus::Qualified]);
    Lead::factory()->create(['tenant_id' => $this->tenant->id, 'status' => LeadStatus::Lost]);

    $pipeline = Pipeline::factory()->withDefaultStages()->create(['tenant_id' => $this->tenant->id]);
    Deal::factory()->create([
        'tenant_id' => $this->tenant->id,
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $pipeline->stages()->value('id'),
    ]);

    Tender::factory()->create(['reference' => 'OPEN-IT']);
    Tender::factory()->closed()->create(['reference' => 'CLOSED-IT']);

    $page = $this->actingAs($this->user)
        ->get('/dashboard')
        ->assertOk()
        ->viewData('page')['props'];

    expect($page['metrics'])->toBe([
        ['key' => 'leads', 'label' => 'Open leads', 'value' => 1, 'href' => '/leads'],
        ['key' => 'qualified', 'label' => 'Qualified leads', 'value' => 1, 'href' => '/leads'],
        ['key' => 'deals', 'label' => 'Open deals', 'value' => 1, 'href' => '/deals'],
        ['key' => 'tenders', 'label' => 'Open IT tenders', 'value' => 1, 'href' => '/tenders'],
    ]);

    expect($page['pipeline']['stages'])->toHaveCount(8)
        ->and(collect($page['connections'])->pluck('key')->all())->toBe([
            'meta', 'linkedin', 'upwork', 'whatsapp', 'google',
        ])
        ->and($page['activity'])->toHaveCount(7)
        ->and($page['leads'])->toHaveCount(2);
});
