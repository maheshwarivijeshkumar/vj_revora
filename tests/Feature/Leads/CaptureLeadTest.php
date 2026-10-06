<?php

declare(strict_types=1);

use App\Domain\Leads\Actions\CaptureLead;
use App\Domain\Leads\DataObjects\NormalizedLead;
use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Leads\Enums\ScoreBand;
use App\Domain\Leads\Events\LeadCaptured;
use App\Domain\Leads\Exceptions\UnusableLeadException;
use App\Domain\Leads\Services\LeadDeduplicator;
use App\Domain\Leads\Services\LeadNormalizer;
use App\Domain\Leads\Services\LeadScorer;
use App\Domain\Tenancy\TenantContext;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\LeadScoreRule;
use App\Models\LeadSource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/*
|--------------------------------------------------------------------------
| Lead capture pipeline
|--------------------------------------------------------------------------
|
| normalize → deduplicate → score → assign → emit (§5, §85).
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);

    foreach (LeadScorer::defaultRules() as $i => $rule) {
        LeadScoreRule::create([...$rule, 'sort_order' => $i]);
    }

    $this->capture = app(CaptureLead::class);
    $this->normalizer = app(LeadNormalizer::class);
});

function normalize(array $payload): NormalizedLead
{
    return app(LeadNormalizer::class)->normalize($payload);
}

// --- Normalization (§17) ----------------------------------------------------

it('maps however a provider spells its field names', function (): void {
    $lead = normalize([
        'FirstName' => 'Amara',
        'last-name' => 'Okafor',
        'Email Address' => 'AMARA@Acme.Example ',
        'mobile_number' => '+971 50 123 4567',
        'company' => 'Acme',
    ]);

    expect($lead->firstName)->toBe('Amara')
        ->and($lead->lastName)->toBe('Okafor')
        ->and($lead->fullName)->toBe('Amara Okafor')
        ->and($lead->normalizedEmail())->toBe('amara@acme.example')
        // Formatting differences collapse, so the same person submitted twice
        // with different spacing still deduplicates.
        ->and($lead->normalizedPhone())->toBe('+971501234567')
        ->and($lead->companyName)->toBe('Acme');
});

it('splits a full name when the provider sends only one', function (): void {
    expect(normalize(['name' => 'Priya Nair'])->firstName)->toBe('Priya')
        ->and(normalize(['name' => 'Priya Nair'])->lastName)->toBe('Nair')
        // A single token is a first name, not a surname.
        ->and(normalize(['name' => 'Cher'])->firstName)->toBe('Cher')
        ->and(normalize(['name' => 'Cher'])->lastName)->toBeNull();
});

it('keeps unrecognised provider fields instead of discarding them', function (): void {
    $lead = normalize([
        'email' => 'a@b.example',
        'budget' => '50k',
        'how_did_you_hear' => 'Podcast',
    ]);

    // §17 forbids forcing provider-specific fields into core columns, and
    // dropping them would lose the answers the form was asked for.
    expect($lead->metadata)->toMatchArray([
        'budget' => '50k',
        'how_did_you_hear' => 'Podcast',
    ]);
});

it('reads fields out of nested provider payloads', function (): void {
    $lead = normalize([
        'entry' => ['changes' => ['value' => ['email' => 'nested@example.com']]],
    ]);

    expect($lead->normalizedEmail())->toBe('nested@example.com');
});

it('captures utm attribution separately from metadata', function (): void {
    $lead = normalize([
        'email' => 'a@b.example',
        'utm_source' => 'linkedin',
        'utm_campaign' => 'launch',
    ]);

    expect($lead->utm)->toBe(['utm_source' => 'linkedin', 'utm_campaign' => 'launch'])
        ->and($lead->metadata)->not->toHaveKey('utm_source');
});

// --- Capture ----------------------------------------------------------------

it('captures a new lead, scores it and routes it', function (): void {
    Event::fake([LeadCaptured::class]);

    $owner = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $source = LeadSource::factory()->create(['tenant_id' => $this->tenant->id]);

    $result = $this->capture->handle(
        normalize([
            'first_name' => 'Amara',
            'last_name' => 'Okafor',
            'email' => 'amara@acme.example',
            'phone' => '+971501234567',
            'company' => 'Acme',
            'job_title' => 'Head of Growth',
            'interest' => 'Requesting a demo',
            'budget' => '50k',
            'consent' => 'on',
        ]),
        $source,
    );

    expect($result->isNew())->toBeTrue()
        ->and($result->lead->status)->toBe(LeadStatus::New)
        ->and($result->lead->lead_source_id)->toBe($source->id)
        ->and($result->assignedTo)->toBe($owner->id);

    // 10 email + 10 phone + 10 company + 5 title + 25 demo + 15 budget + 5 consent
    expect($result->score->score)->toBe(80)
        ->and($result->score->band)->toBe(ScoreBand::High);

    Event::assertDispatched(LeadCaptured::class);
});

it('records consent however the form spelled it', function (): void {
    // §88: consent is captured at the moment it is given, never inferred.
    expect(normalize(['email' => 'a@b.example', 'consent' => 'on'])->consent)->toBeTrue()
        ->and(normalize(['email' => 'a@b.example', 'opt_in' => '1'])->consent)->toBeTrue()
        ->and(normalize(['email' => 'a@b.example', 'marketing_consent' => 'yes'])->consent)->toBeTrue()
        // Anything unrecognised means not consented, never assumed.
        ->and(normalize(['email' => 'a@b.example', 'consent' => 'no'])->consent)->toBeFalse()
        ->and(normalize(['email' => 'a@b.example'])->consent)->toBeFalse();
});

it('stamps consent_at only when consent was actually given', function (): void {
    $withConsent = $this->capture->handle(normalize(['email' => 'yes@example.com', 'consent' => 'true']));
    $without = $this->capture->handle(normalize(['email' => 'no@example.com']));

    expect($withConsent->lead->consent)->toBeTrue()
        ->and($withConsent->lead->consent_at)->not->toBeNull()
        ->and($without->lead->consent)->toBeFalse()
        ->and($without->lead->consent_at)->toBeNull();
});

it('explains every score it gives', function (): void {
    $result = $this->capture->handle(normalize(['email' => 'a@b.example']));

    // §19: a score without reasons is a number a rep cannot act on.
    expect($result->score->reasons)->toContain(['label' => 'Email provided', 'points' => 10])
        ->and($result->score->score)->toBe(10);
});

it('refuses a payload with nothing identifiable in it', function (): void {
    $this->capture->handle(normalize(['favourite_colour' => 'blue']));
})->throws(UnusableLeadException::class);

it('records a timeline entry for every pipeline step', function (): void {
    User::factory()->create(['tenant_id' => $this->tenant->id]);

    $result = $this->capture->handle(normalize(['email' => 'timeline@example.com']));

    expect(LeadEvent::where('lead_id', $result->lead->id)->pluck('type'))
        ->toContain(LeadEvent::CAPTURED, LeadEvent::SCORED, LeadEvent::ASSIGNED);
});

// --- Deduplication (§18) ----------------------------------------------------

it('matches an existing lead on normalised email', function (): void {
    $this->capture->handle(normalize(['email' => 'dupe@example.com', 'first_name' => 'First']));

    $second = $this->capture->handle(normalize([
        'email' => '  DUPE@Example.COM ',
        'company' => 'Acme Ltd',
    ]));

    expect($second->isDuplicate)->toBeTrue()
        ->and($second->matchedOn)->toBe(LeadDeduplicator::MATCH_EMAIL)
        ->and(Lead::count())->toBe(1)
        // The second submission filled a gap rather than being dropped.
        ->and($second->lead->company_name)->toBe('Acme Ltd');
});

it('matches on phone however the number was written', function (): void {
    $this->capture->handle(normalize(['email' => 'one@example.com', 'phone' => '+971501234567']));

    // All three dial the same handset, so all three are the same person. The
    // digits-only normaliser this replaced could not see that: `00971…` kept no
    // country code, so it never matched `+971…` and one person became two
    // leads (§18).
    foreach (['00971501234567', '+971 50 123 4567', '+971-50-123-4567'] as $written) {
        $result = $this->capture->handle(normalize(['phone' => $written]));

        expect($result->isDuplicate)->toBeTrue()
            ->and($result->matchedOn)->toBe(LeadDeduplicator::MATCH_PHONE);
    }

    expect(Lead::count())->toBe(1);
});

it('prefers an external id over weaker signals', function (): void {
    $byEmail = $this->capture->handle(normalize(['email' => 'shared@example.com']));

    $external = Lead::create([
        'email' => 'other@example.com',
        'external_system' => 'salesforce',
        'external_record_id' => 'SF-1',
    ]);

    $result = $this->capture->handle(normalize([
        'email' => 'shared@example.com',
    ]), null);

    // Email match still wins here because no external id was supplied.
    expect($result->lead->id)->toBe($byEmail->lead->id)
        ->and($external->id)->not->toBe($byEmail->lead->id);
});

it('never overwrites a value that is already set', function (): void {
    $first = $this->capture->handle(normalize([
        'email' => 'keep@example.com',
        'company' => 'Original Ltd',
    ]));

    $this->capture->handle(normalize([
        'email' => 'keep@example.com',
        'company' => 'Different Ltd',
    ]));

    // The earlier value was verified by whatever created it; a later form fill
    // is not automatically more correct (§18).
    expect($first->lead->refresh()->company_name)->toBe('Original Ltd');
});

it('does not reassign a lead that someone is already working', function (): void {
    $first = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $result = $this->capture->handle(normalize(['email' => 'owned@example.com']));
    expect($result->lead->owner_id)->toBe($first->id);

    // A second rep joins, then the same person submits again.
    User::factory()->create(['tenant_id' => $this->tenant->id]);
    $second = $this->capture->handle(normalize(['email' => 'owned@example.com']));

    expect($second->assignedTo)->toBeNull()
        ->and($second->lead->owner_id)->toBe($first->id);
});

it('keeps first touch fixed while last touch advances', function (): void {
    $first = $this->capture->handle(normalize([
        'email' => 'touch@example.com',
        'utm_source' => 'google',
        'landing_page' => 'https://example.test/a',
    ]));

    $this->capture->handle(normalize([
        'email' => 'touch@example.com',
        'utm_source' => 'linkedin',
        'landing_page' => 'https://example.test/b',
    ]));

    $lead = $first->lead->refresh();

    // Attribution is only meaningful if first touch stays put (§34).
    expect($lead->first_touch['utm']['utm_source'])->toBe('google')
        ->and($lead->last_touch['utm']['utm_source'])->toBe('linkedin');
});

// --- Merging (§18) ----------------------------------------------------------

it('retires a duplicate as a tombstone rather than deleting it', function (): void {
    $master = Lead::factory()->create(['tenant_id' => $this->tenant->id]);
    $duplicate = Lead::factory()->create(['tenant_id' => $this->tenant->id]);

    LeadEvent::create([
        'lead_id' => $duplicate->id,
        'type' => LeadEvent::CAPTURED,
        'occurred_at' => now(),
    ]);

    app(LeadDeduplicator::class)->tombstone($master, $duplicate, LeadDeduplicator::MATCH_EMAIL);

    // The row survives so its history and attribution are not lost, but it is
    // excluded from every list and count.
    expect($duplicate->refresh()->merged_into_id)->toBe($master->id)
        ->and(Lead::master()->count())->toBe(1)
        ->and(Lead::count())->toBe(2)
        // Its events follow the person, so the master's timeline is complete.
        ->and(LeadEvent::where('lead_id', $master->id)->count())->toBe(1);
});

it('will not match a lead that was already merged away', function (): void {
    $master = Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'merged@example.com',
    ]);
    $duplicate = Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'merged@example.com',
    ]);

    app(LeadDeduplicator::class)->tombstone($master, $duplicate, LeadDeduplicator::MATCH_EMAIL);

    $result = $this->capture->handle(normalize(['email' => 'merged@example.com']));

    expect($result->lead->id)->toBe($master->id);
});

// --- Tenant isolation -------------------------------------------------------

it('never matches a lead belonging to another workspace', function (): void {
    $other = Tenant::factory()->create();

    app(TenantContext::class)->runAs($other, function (): void {
        Lead::create(['email' => 'cross@example.com']);
    });

    $result = $this->capture->handle(normalize(['email' => 'cross@example.com']));

    // Deduplication must never reach across the workspace boundary, or one
    // customer's leads would leak into another's through a merge.
    expect($result->isDuplicate)->toBeFalse()
        ->and($result->lead->tenant_id)->toBe($this->tenant->id);
});

// --- Bands (§119) -----------------------------------------------------------

it('bands a score against the configured thresholds', function (int $score, ScoreBand $band): void {
    expect(ScoreBand::forScore($score))->toBe($band);
})->with([
    [0, ScoreBand::Low],
    [39, ScoreBand::Low],
    [40, ScoreBand::Medium],
    [69, ScoreBand::Medium],
    [70, ScoreBand::High],
    [89, ScoreBand::High],
    [90, ScoreBand::VeryHigh],
    [100, ScoreBand::VeryHigh],
]);

it('clamps a runaway rule set to 100', function (): void {
    LeadScoreRule::create([
        'name' => 'Overshoot',
        'condition' => ['field' => 'email', 'operator' => 'is_present'],
        'points' => 500,
        'sort_order' => 99,
    ]);

    expect($this->capture->handle(normalize(['email' => 'max@example.com']))->score->score)
        ->toBe(100);
});

it('ignores a malformed rule rather than failing ingestion', function (): void {
    LeadScoreRule::create([
        'name' => 'Broken',
        'condition' => ['operator' => 'equals'],
        'points' => 50,
        'sort_order' => 99,
    ]);

    // One bad rule must not take down capture for every lead behind it.
    $result = $this->capture->handle(normalize(['email' => 'ok@example.com']));

    expect($result->score->score)->toBe(10);
});
