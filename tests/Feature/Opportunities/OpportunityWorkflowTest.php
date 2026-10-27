<?php

declare(strict_types=1);

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Messaging\Actions\ProgressLeadStatus;
use App\Domain\Opportunities\Actions\EnsureServiceProfiles;
use App\Domain\Opportunities\Enums\OpportunityStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\EmailTemplate;
use App\Models\FollowUpSequence;
use App\Models\JobPost;
use App\Models\Lead;
use App\Models\OpportunityMatch;
use App\Models\ServiceProfile;
use App\Models\Tenant;
use App\Models\Tender;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Opportunities, profiles, playbook and pipeline moves
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

    $permissions = ['lead.view', 'lead.update', 'lead.create', 'settings.view', 'settings.manage', 'integration.connect'];

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('Owner', 'web');
    $role->syncPermissions($permissions);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);

    config(['verification.queue_recheck' => false, 'services.ai.provider' => null]);
    Http::preventStrayRequests();
});

function tenderMatch(array $tender = []): OpportunityMatch
{
    app(EnsureServiceProfiles::class)->handle();
    $model = Tender::factory()->create(['title' => 'Development of a web portal and MIS', 'reference' => 'TS0000099999E', ...$tender]);

    return OpportunityMatch::create([
        'opportunity_type' => 'tender', 'opportunity_id' => $model->id,
        'service_profile_id' => ServiceProfile::default()->id, 'fit_score' => 80,
        'fit_details' => ['services' => ['Web Development'], 'keywords' => [], 'reasons' => []], 'status' => 'new',
    ]);
}

it('sets up both profiles and a template for every stage from Settings → Services', function (): void {
    $this->actingAs($this->user)->post('/settings/services/install')->assertSessionHas('success');

    $sequence = FollowUpSequence::query()->with('steps.template')->sole();

    expect(ServiceProfile::query()->orderBy('id')->pluck('name')->all())->toBe(['Berg Media Digital', 'Vijesh Kumar Maheshwari'])
        ->and(EmailTemplate::query()->where('name', 'like', 'Berg Media · %')->count())->toBe(11)
        ->and(EmailTemplate::query()->where('name', 'like', 'Vijesh · %')->count())->toBe(11)
        ->and($sequence->steps->pluck('template.name')->all())->toBe([
            'Berg Media · 1. First reply', 'Berg Media · 2. Follow-up (6 hours)', 'Berg Media · 3. Last follow-up (24 hours)',
        ]);

    // Running it again changes nothing.
    $this->actingAs($this->user)->post('/settings/services/install');
    expect(EmailTemplate::query()->count())->toBe(25);
});

it('lists strong matches and hides weak ones by default', function (): void {
    $strong = tenderMatch();
    OpportunityMatch::create([
        'opportunity_type' => 'job_post',
        'opportunity_id' => JobPost::query()->create([
            'source' => 'remoteok', 'external_id' => 'w', 'title' => 'Weak', 'url' => 'https://x', 'fingerprint' => 'w',
            'first_seen_at' => now(), 'last_seen_at' => now(),
        ])->id,
        'fit_score' => 40, 'status' => 'new',
    ]);

    $props = $this->actingAs($this->user)->get('/opportunities')->assertOk()->viewData('page')['props'];

    expect(array_column($props['matches']['data'], 'id'))->toBe([$strong->uuid])
        ->and($props['matches']['data'][0]['reference'])->toBe('TS0000099999E');
});

it('drafts a tender letter from a template when no AI is configured', function (): void {
    $match = tenderMatch();

    $this->actingAs($this->user)->post("/opportunities/{$match->uuid}/draft")->assertSessionHas('success');

    $match->refresh();
    expect($match->status)->toBe(OpportunityStatus::Drafted)
        ->and($match->drafted_by)->toBe('template')
        ->and($match->draft)->toContain('TS0000099999E')
        ->and($match->draft)->toContain('Berg Media Digital')
        ->and($match->draft)->toContain('Before bidding:');
});

it('drafts with Claude, from the profile\'s facts, when it is configured', function (): void {
    config(['services.ai.provider' => 'claude', 'services.anthropic.key' => 'sk-test', 'services.anthropic.model' => 'claude-opus-5-5']);
    Http::fake(['api.anthropic.com/v1/messages' => Http::response([
        'stop_reason' => 'end_turn',
        'content' => [['type' => 'text', 'text' => 'To the Procurement Committee, …']],
    ])]);
    $match = tenderMatch();

    $this->actingAs($this->user)->post("/opportunities/{$match->uuid}/draft");

    expect($match->fresh()->drafted_by)->toBe('claude');
    Http::assertSent(fn (Request $request): bool => $request['model'] === 'claude-opus-5-5'
        && $request['fallbacks'] === 'default'
        && str_contains((string) $request['system'], 'Never invent clients')
        && str_contains((string) $request['system'], 'Berg Media Digital')
        && str_contains((string) $request['messages'][0]['content'], 'TS0000099999E'));
});

it('adds an opportunity to the leads pipeline once', function (): void {
    $match = tenderMatch(['department' => 'National Grid Company']);

    $this->actingAs($this->user)->post("/opportunities/{$match->uuid}/convert")->assertRedirect();
    $this->actingAs($this->user)->post("/opportunities/{$match->uuid}/convert");

    $lead = Lead::query()->sole();
    expect($lead->company_name)->toBe('National Grid Company')
        ->and($match->fresh()->lead_id)->toBe($lead->id);
});

it('marks an opportunity applied, won or lost', function (): void {
    $match = tenderMatch();

    $this->actingAs($this->user)->patch("/opportunities/{$match->uuid}", ['status' => 'applied'])->assertSessionHas('success');

    expect($match->fresh()->status)->toBe(OpportunityStatus::Applied);
});

it('does not let one workspace touch another\'s opportunity', function (): void {
    $other = Tenant::factory()->create();
    $foreign = app(TenantContext::class)->runAs($other, fn () => tenderMatch());

    $this->actingAs($this->user)->patch("/opportunities/{$foreign->uuid}", ['status' => 'dismissed'])->assertNotFound();
});

it('moves leads along the pipeline from the conversation, but never over a person\'s choice', function (): void {
    $progress = app(ProgressLeadStatus::class);
    $lead = fn (LeadStatus $status): Lead => Lead::factory()->create(['tenant_id' => $this->tenant->id, 'status' => $status]);

    $new = $lead(LeadStatus::New);
    $progress->afterFirstEmail($new);
    expect($new->fresh()->status)->toBe(LeadStatus::Contacted);

    $keen = $lead(LeadStatus::Contacted);
    $progress->afterReply($keen, 9);
    expect($keen->fresh()->status)->toBe(LeadStatus::Qualified)
        ->and($keen->fresh()->qualified_at)->not->toBeNull();

    $no = $lead(LeadStatus::Contacted);
    $progress->afterReply($no, 1);
    expect($no->fresh()->status)->toBe(LeadStatus::Lost);

    $silent = $lead(LeadStatus::Contacted);
    $progress->afterNoReply($silent);
    expect($silent->fresh()->status)->toBe(LeadStatus::Nurturing);

    $booked = $lead(LeadStatus::Appointment);
    $progress->afterReply($booked, 1);
    expect($booked->fresh()->status)->toBe(LeadStatus::Appointment);
});

it('shows what is connected on the setup page', function (): void {
    app(EnsureServiceProfiles::class)->handle();

    $props = $this->actingAs($this->user)->get('/settings/setup')->assertOk()->viewData('page')['props'];
    $steps = collect($props['steps'])->keyBy('key');

    // The freelancer profile still has the "paste your resume" note.
    expect($steps['profiles']['done'])->toBeFalse()
        ->and($steps['mailbox']['done'])->toBeFalse()
        ->and($steps->has('upwork'))->toBeTrue();
});

it('re-scores matches when a profile\'s services change', function (): void {
    $match = tenderMatch();
    $profile = ServiceProfile::default();

    $this->actingAs($this->user)->put("/settings/services/{$profile->uuid}", [
        'name' => $profile->name, 'kind' => 'agency', 'is_default' => true,
        'services' => [['name' => 'Branding', 'summary' => '', 'keywords' => 'logo design']],
        'portfolio' => [],
    ])->assertSessionHasNoErrors();

    expect($match->fresh()->fit_score)->toBeLessThan(80);
});
