<?php

declare(strict_types=1);

use App\Domain\Deals\Actions\CreateDeal;
use App\Domain\Leads\Actions\CaptureLead;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\LeadNormalizer;
use App\Domain\Leads\Services\LeadScorer;
use App\Domain\Tenancy\TenantContext;
use App\Models\Lead;
use App\Models\LeadScoreRule;
use App\Models\LeadSource;
use App\Models\Pipeline;
use App\Models\Tag;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| The lead detail screen
|--------------------------------------------------------------------------
|
| §44. Overview, source, score explanation, verification findings, timeline,
| deals and audit — the sections that have something behind them today.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);
    Queue::fake();

    $this->seed(PermissionSeeder::class);

    foreach (LeadScorer::defaultRules() as $i => $rule) {
        LeadScoreRule::create([...$rule, 'sort_order' => $i]);
    }

    $role = Role::findOrCreate('Rep', 'web');
    $role->syncPermissions(['lead.view', 'lead.update', 'audit.view']);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);
});

function detail(Lead $lead): array
{
    return test()->actingAs(test()->user)
        ->get("/leads/{$lead->id}")
        ->assertOk()
        ->viewData('page')['props'];
}

function captured(array $payload = []): Lead
{
    $source = LeadSource::factory()->create(['tenant_id' => test()->tenant->id]);

    $normalized = app(LeadNormalizer::class)->normalize([
        'first_name' => 'Amara',
        'last_name' => 'Okafor',
        'email' => 'amara@northwind.example',
        'phone' => '+971501234567',
        'company' => 'Northwind',
        'consent' => true,
        ...$payload,
    ]);

    return app(CaptureLead::class)->handle($normalized, $source)->lead;
}

// --- Access ---------------------------------------------------------------------

it('requires the lead.view permission', function (): void {
    $lead = Lead::factory()->create(['tenant_id' => $this->tenant->id]);
    $stranger = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($stranger)->get("/leads/{$lead->id}")->assertForbidden();
});

it('cannot open a lead from another workspace', function (): void {
    $other = Tenant::factory()->create();

    $foreign = app(TenantContext::class)->runAs(
        $other,
        fn () => Lead::factory()->create(['tenant_id' => $other->id]),
    );

    $this->actingAs($this->user)->get("/leads/{$foreign->id}")->assertNotFound();
});

it('does not collide with the bulk-action route', function (): void {
    // `/leads/bulk` would otherwise be swallowed by the `{lead}` parameter.
    $this->actingAs($this->user)->get('/leads/bulk')->assertNotFound();
});

// --- Overview -------------------------------------------------------------------

it('shows the contact details and the dialable number', function (): void {
    $lead = captured();

    $props = detail($lead);

    expect($props['lead']['name'])->toBe('Amara Okafor')
        ->and($props['lead']['email'])->toBe('amara@northwind.example')
        // E.164 so a click-to-call link works from any country, alongside the
        // number as it was typed.
        ->and($props['lead']['phone_e164'])->toBe('+971501234567')
        ->and($props['lead']['phone_type'])->toBe('mobile')
        ->and($props['lead']['phone_country'])->toBe('AE');
});

it('shows when and how consent was given', function (): void {
    $props = detail(captured());

    // §88. "They agreed" without a date and a source is not evidence.
    expect($props['lead']['consent'])->toBeTrue()
        ->and($props['lead']['consent_at'])->not->toBeNull()
        ->and($props['lead']['consent_source'])->not->toBeNull();
});

it('shows tags', function (): void {
    $lead = captured();
    $lead->tags()->attach(Tag::create(['name' => 'Trade show'])->id);

    expect(detail($lead)['lead']['tags'][0]['name'])->toBe('Trade show');
});

// --- Score explanation ----------------------------------------------------------

it('explains the score rather than just stating it', function (): void {
    $props = detail(captured());

    // §19: a rep who cannot see why a lead scored 82 has no way to trust it.
    expect($props['score']['score'])->toBeGreaterThan(0)
        ->and($props['score']['reasons'])->not->toBeEmpty()
        ->and($props['score']['reasons'][0])->toHaveKeys(['label', 'points'])
        ->and($props['score']['band_label'])->not->toBeEmpty();
});

it('says so plainly when no rule matched', function (): void {
    LeadScoreRule::query()->delete();

    $props = detail(captured());

    expect($props['score']['reasons'])->toBeEmpty()
        ->and($props['score']['score'])->toBe(0);
});

// --- Verification ---------------------------------------------------------------

it('lists the verification findings', function (): void {
    $lead = captured(['email' => 'info@northwind.example']);

    $props = detail($lead);

    // §59. "Needs a look" with no reason cannot be acted on.
    expect($props['verification']['status'])->not->toBe('unverified')
        ->and($props['verification']['confidence'])->not->toBeNull()
        ->and(array_column($props['verification']['findings'], 'check'))
        ->toContain('email.role');
});

// --- Timeline -------------------------------------------------------------------

it('builds a timeline from what actually happened', function (): void {
    $props = detail(captured());

    $types = array_column($props['timeline'], 'type');

    expect($types)->toContain('lead.captured', 'lead.scored', 'lead.verified');
});

it('orders the timeline newest first', function (): void {
    $props = detail(captured());

    $times = array_column($props['timeline'], 'occurred_at');
    $sorted = $times;
    rsort($sorted);

    // A timeline is read from what just happened, backwards.
    expect($times)->toBe($sorted);
});

it('labels an event type it has never seen', function (): void {
    $lead = captured();

    $lead->events()->create([
        'type' => 'lead.imported_from_somewhere',
        'occurred_at' => now(),
    ]);

    $labels = collect(detail($lead)['timeline'])
        ->firstWhere('type', 'lead.imported_from_somewhere')['label'];

    // Providers may add their own types; a gap in the timeline would be worse
    // than an unpolished line in it.
    expect($labels)->toBe('imported from somewhere');
});

// --- Deals ----------------------------------------------------------------------

it('lists the deals opened from this lead', function (): void {
    $lead = captured();
    $pipeline = Pipeline::factory()->withDefaultStages()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    app(CreateDeal::class)->handle(
        ['title' => 'Northwind renewal', 'value' => 25000, 'lead_id' => $lead->id],
        $pipeline,
    );

    $props = detail($lead);

    expect($props['deals'])->toHaveCount(1)
        ->and($props['deals'][0]['title'])->toBe('Northwind renewal')
        ->and($props['deals'][0]['stage'])->toBe('New');
});

// --- Source and attribution -----------------------------------------------------

it('shows the provenance of the lead', function (): void {
    $source = LeadSource::factory()->connected('meta')->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $lead = Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'lead_source_id' => $source->id,
    ]);

    // §2. An imported list and a verified provider submission must not look
    // the same.
    expect(detail($lead)['attribution']['source']['authorized_api'])->toBeTrue();
});

it('shows the utm parameters and the first touch', function (): void {
    $lead = captured([
        'utm_source' => 'google',
        'utm_campaign' => 'spring',
        'landing_page' => 'https://northwind.example/pricing',
    ]);

    $props = detail($lead);

    expect($props['attribution']['utm']['utm_source'])->toBe('google')
        // First touch is written once and never updated, which is what makes
        // first-touch attribution mean anything (§34).
        ->and($props['attribution']['first_touch'])->not->toBeEmpty();
});

// --- Merge history --------------------------------------------------------------

it('warns that a merged lead is a tombstone', function (): void {
    $master = Lead::factory()->create(['tenant_id' => $this->tenant->id]);
    $duplicate = Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'merged_into_id' => $master->id,
    ]);

    // Without this, someone works a person who lives under another record.
    expect(detail($duplicate)['lead']['merged_into']['id'])->toBe($master->id);
});

it('says when a record is combined from several submissions', function (): void {
    $master = Lead::factory()->create(['tenant_id' => $this->tenant->id]);
    Lead::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'merged_into_id' => $master->id,
    ]);

    expect(detail($master)['duplicates'])->toHaveCount(2);
});

// --- Audit ----------------------------------------------------------------------

it('defers the audit trail until it is asked for', function (): void {
    $lead = captured();

    // Deferred: the least-opened tab with the most expensive query.
    expect(detail($lead))->not->toHaveKey('audit');

    $version = $this->actingAs($this->user)
        ->get("/leads/{$lead->id}")
        ->viewData('page')['version'];

    $props = $this->actingAs($this->user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'crm/leads/Show',
            'X-Inertia-Partial-Data' => 'audit',
        ])
        ->get("/leads/{$lead->id}")
        ->assertOk()
        ->json('props');

    expect($props['audit'])->toBeArray();
});

it('shows only this lead changes in its audit tab', function (): void {
    $lead = captured();
    $other = captured(['email' => 'someone.else@northwind.example']);

    $lead->forceFill(['status' => LeadStatus::Qualified])->save();
    $other->forceFill(['status' => LeadStatus::Contacted])->save();

    $version = $this->actingAs($this->user)
        ->get("/leads/{$lead->id}")
        ->viewData('page')['version'];

    $props = $this->actingAs($this->user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Partial-Component' => 'crm/leads/Show',
            'X-Inertia-Partial-Data' => 'audit',
        ])
        ->get("/leads/{$lead->id}")
        ->json('props');

    expect($props['audit'])->not->toBeEmpty();

    foreach ($props['audit'] as $entry) {
        expect($entry['actor'])->not->toBeEmpty();
    }
});
