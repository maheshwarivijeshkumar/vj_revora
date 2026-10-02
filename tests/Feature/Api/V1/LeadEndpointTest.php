<?php

declare(strict_types=1);

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Services\LeadScorer;
use App\Domain\Tenancy\TenantContext;
use App\Models\ApiKey;
use App\Models\Lead;
use App\Models\LeadScoreRule;
use App\Models\LeadSource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| Lead endpoints
|--------------------------------------------------------------------------
|
| §47. These call the same domain actions the UI uses, so deduplication,
| scoring and routing behave identically whichever door a lead comes through.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);

    foreach (LeadScorer::defaultRules() as $i => $rule) {
        LeadScoreRule::create([...$rule, 'sort_order' => $i]);
    }

    LeadSource::factory()->create(['tenant_id' => $this->tenant->id, 'key' => 'api']);

    $this->token = ApiKey::mint('Test', ['leads.write'])['token'];
});

function api(string $method, string $path, array $body = []): TestResponse
{
    return test()
        ->withHeader('Authorization', 'Bearer '.test()->token)
        ->json($method, $path, $body);
}

// --- Creating ---------------------------------------------------------------

it('creates a lead and returns 201', function (): void {
    $response = api('POST', '/api/v1/leads', [
        'first_name' => 'Amara',
        'last_name' => 'Okafor',
        'email' => 'amara@acme.example',
        'company' => 'Acme',
        'consent' => true,
    ])->assertCreated();

    expect($response->json('data.full_name'))->toBe('Amara Okafor')
        ->and($response->json('data.company_name'))->toBe('Acme')
        ->and($response->json('data.status'))->toBe('new')
        // The public identifier is the uuid; internal ids stay internal so a
        // schema change cannot break an integration.
        ->and($response->json('data.id'))->toBe(Lead::first()->uuid)
        ->and($response->json('data'))->not->toHaveKey('tenant_id');
});

it('scores a lead created over the api exactly as one from the ui', function (): void {
    $response = api('POST', '/api/v1/leads', [
        'email' => 'scored@acme.example',
        'phone' => '+971501234567',
        'company' => 'Acme',
        'consent' => true,
    ])->assertCreated();

    // 10 email + 10 phone + 10 company + 5 consent
    expect($response->json('meta.score.score'))->toBe(35)
        ->and($response->json('meta.score.reasons'))->not->toBeEmpty();
});

it('returns 200 and flags a duplicate rather than creating a second lead', function (): void {
    api('POST', '/api/v1/leads', ['email' => 'dupe@acme.example'])->assertCreated();

    $second = api('POST', '/api/v1/leads', [
        'email' => 'DUPE@acme.example',
        'company' => 'Acme Ltd',
    ])->assertOk();

    // 201 would tell a retrying client it had made a second lead.
    expect($second->json('meta.is_duplicate'))->toBeTrue()
        ->and($second->json('meta.matched_on'))->toBe('email')
        ->and(Lead::count())->toBe(1)
        ->and($second->json('data.company_name'))->toBe('Acme Ltd');
});

it('refuses a payload with nothing identifiable in it', function (): void {
    api('POST', '/api/v1/leads', ['job_title' => 'CTO'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('accepts a lead identified only by phone', function (): void {
    api('POST', '/api/v1/leads', ['phone' => '+971501234567'])->assertCreated();
});

it('validates field formats', function (): void {
    api('POST', '/api/v1/leads', ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    api('POST', '/api/v1/leads', ['email' => 'a@b.example', 'country' => 'United Arab Emirates'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('country');
});

it('accepts the inbound alias used by partner sites', function (): void {
    // §84. Same handler: an inbound lead is a lead, and a second pipeline
    // would mean two of everything to keep in step.
    api('POST', '/api/v1/inbound/leads', ['email' => 'partner@acme.example'])
        ->assertCreated();

    expect(Lead::count())->toBe(1);
});

it('keeps unrecognised fields as metadata', function (): void {
    $response = api('POST', '/api/v1/leads', [
        'email' => 'meta@acme.example',
        'budget' => '50k',
    ])->assertCreated();

    expect($response->json('data.metadata.budget'))->toBe('50k');
});

// --- Reading ----------------------------------------------------------------

it('lists leads with pagination metadata', function (): void {
    Lead::factory()->count(30)->create(['tenant_id' => $this->tenant->id]);

    $response = api('GET', '/api/v1/leads?per_page=10')->assertOk();

    expect($response->json('data'))->toHaveCount(10)
        ->and($response->json('meta.total'))->toBe(30);
});

it('caps per_page so a caller cannot ask for everything', function (): void {
    Lead::factory()->count(20)->create(['tenant_id' => $this->tenant->id]);

    expect(api('GET', '/api/v1/leads?per_page=5000')->json('meta.per_page'))->toBe(100);
});

it('filters by status, email and update time', function (): void {
    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => LeadStatus::Qualified,
        'email' => 'find@acme.example',
    ]);
    Lead::factory()->create(['tenant_id' => $this->tenant->id, 'status' => LeadStatus::New]);

    expect(api('GET', '/api/v1/leads?status[]=qualified')->json('meta.total'))->toBe(1)
        // Matched on the normalised form, so case and spacing do not matter.
        ->and(api('GET', '/api/v1/leads?email=FIND@acme.example')->json('meta.total'))->toBe(1)
        // urlencoded: an ISO8601 offset contains a '+', which a query string
        // decodes as a space.
        ->and(api('GET', '/api/v1/leads?updated_since='.urlencode(now()->addDay()->toIso8601String()))->json('meta.total'))->toBe(0);
});

it('excludes merged duplicates from the list', function (): void {
    $master = Lead::factory()->create(['tenant_id' => $this->tenant->id]);
    Lead::factory()->create(['tenant_id' => $this->tenant->id, 'merged_into_id' => $master->id]);

    expect(api('GET', '/api/v1/leads')->json('meta.total'))->toBe(1);
});

it('shows provenance on the source', function (): void {
    $source = LeadSource::factory()->connected('meta')->create(['tenant_id' => $this->tenant->id]);
    $lead = Lead::factory()->create(['tenant_id' => $this->tenant->id, 'lead_source_id' => $source->id]);

    // §2: a caller must be able to tell an authorized provider feed from an
    // uploaded list.
    expect(api('GET', "/api/v1/leads/{$lead->uuid}")->json('data.source.authorized_api'))
        ->toBeTrue();
});

// --- Updating ---------------------------------------------------------------

it('updates the fields a caller may set', function (): void {
    $lead = Lead::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = api('PATCH', "/api/v1/leads/{$lead->uuid}", [
        'job_title' => 'Head of Growth',
        'status' => 'qualified',
    ])->assertOk();

    expect($response->json('data.job_title'))->toBe('Head of Growth')
        ->and($response->json('data.status'))->toBe('qualified')
        ->and($lead->refresh()->qualified_at)->not->toBeNull();
});

it('stamps qualified_at only the first time', function (): void {
    $lead = Lead::factory()->create(['tenant_id' => $this->tenant->id]);

    api('PATCH', "/api/v1/leads/{$lead->uuid}", ['status' => 'qualified']);
    $first = $lead->refresh()->qualified_at;

    $this->travel(1)->hour();
    api('PATCH', "/api/v1/leads/{$lead->uuid}", ['status' => 'contacted']);
    api('PATCH', "/api/v1/leads/{$lead->uuid}", ['status' => 'qualified']);

    // Otherwise a later edit silently resets time-to-qualify reporting.
    expect($lead->refresh()->qualified_at->toIso8601String())
        ->toBe($first->toIso8601String());
});

it('will not let a caller write the score or the owner', function (): void {
    $lead = Lead::factory()->scored(20)->create(['tenant_id' => $this->tenant->id]);
    $other = User::factory()->create(['tenant_id' => $this->tenant->id]);

    api('PATCH', "/api/v1/leads/{$lead->uuid}", [
        'score' => 99,
        'owner_id' => $other->id,
    ])->assertOk();

    // The score carries an explanation; letting an integration set it
    // directly would make that explanation untrue (§19).
    expect($lead->refresh()->score)->toBe(20)
        ->and($lead->owner_id)->toBeNull();
});

it('rejects an invalid status', function (): void {
    $lead = Lead::factory()->create(['tenant_id' => $this->tenant->id]);

    api('PATCH', "/api/v1/leads/{$lead->uuid}", ['status' => 'teapot'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

// --- Deleting ---------------------------------------------------------------

it('soft deletes rather than destroying', function (): void {
    $lead = Lead::factory()->create(['tenant_id' => $this->tenant->id]);

    api('DELETE', "/api/v1/leads/{$lead->uuid}")->assertNoContent();

    // An integration bug must not be able to erase a workspace's history.
    expect(Lead::count())->toBe(0)
        ->and(Lead::withTrashed()->count())->toBe(1);
});

it('returns 404 for an unknown uuid', function (): void {
    api('GET', '/api/v1/leads/01932000-0000-7000-8000-000000000000')->assertNotFound();
});
