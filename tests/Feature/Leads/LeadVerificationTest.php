<?php

declare(strict_types=1);

use App\Domain\Leads\DataObjects\VerificationResult;
use App\Domain\Leads\Enums\VerificationStatus;
use App\Domain\Leads\Services\LeadVerifier;
use App\Domain\Tenancy\TenantContext;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Lead verification
|--------------------------------------------------------------------------
|
| "Are these details genuine." §18, and §59 for the requirement that every
| verdict can be explained to the rep looking at it.
|
| Source-independent on purpose: a lead from an authorized provider feed can
| still carry a typo, and one typed by hand can be perfect. Provenance is a
| separate signal (§2).
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    Queue::fake();

    $this->verifier = app(LeadVerifier::class);
});

function lead(array $attributes = []): Lead
{
    return Lead::factory()->make([
        'tenant_id' => test()->tenant->id,
        'email' => null,
        'phone' => null,
        'first_name' => null,
        'last_name' => null,
        'full_name' => null,
        'consent' => true,
        ...$attributes,
    ]);
}

function verify(array $attributes = []): VerificationResult
{
    // MX off by default so the suite never depends on a DNS resolver; the
    // lookup gets its own tests.
    return test()->verifier->verify(lead($attributes), checkMx: false);
}

/**
 * @return list<string>
 */
function checks(array $attributes = []): array
{
    return array_column(verify($attributes)->findings, 'check');
}

// --- A good lead ------------------------------------------------------------

it('passes a named person at a company domain', function (): void {
    $result = verify([
        'email' => 'amara.okafor@northwind.example',
        'phone' => '+971501234567',
        'full_name' => 'Amara Okafor',
    ]);

    expect($result->status)->toBe(VerificationStatus::Valid)
        ->and($result->confidence)->toBe(100)
        ->and($result->findings)->toBeEmpty();
});

it('passes a lead reachable by phone alone', function (): void {
    // Plenty of real leads arrive without an address.
    $result = verify(['phone' => '+971501234567', 'full_name' => 'Amara Okafor']);

    expect($result->status)->toBe(VerificationStatus::Valid);
});

// --- Unreachable ------------------------------------------------------------

it('rejects a lead with no way to reach them', function (): void {
    $result = verify();

    expect($result->status)->toBe(VerificationStatus::Invalid)
        ->and($result->confidence)->toBe(0)
        ->and($result->status->isWorkable())->toBeFalse();
});

it('rejects a malformed address', function (): void {
    expect(verify(['email' => 'amara@@northwind'])->status)
        ->toBe(VerificationStatus::Invalid);
});

it('rejects a throwaway mailbox', function (): void {
    $result = verify([
        'email' => 'someone@mailinator.com',
        'full_name' => 'Amara Okafor',
    ]);

    // Using one is the person telling us they do not want to be reached.
    expect($result->status)->toBe(VerificationStatus::Risky)
        ->and(array_column($result->findings, 'check'))->toContain('email.disposable');
});

// --- Placeholders and bots --------------------------------------------------

it('spots filler typed to get past a required field', function (): void {
    expect(checks(['email' => 'test@northwind.example', 'full_name' => 'Amara Okafor']))
        ->toContain('email.placeholder')
        ->and(checks(['email' => 'amara@northwind.example', 'full_name' => 'John Doe']))
        ->toContain('name.placeholder')
        ->and(checks(['email' => 'amara@northwind.example', 'full_name' => 'asdf']))
        ->toContain('name.placeholder');
});

it('does not mistake a real surname for filler', function (): void {
    // Whole-value matching, so "Tester" survives where "test" does not.
    expect(checks(['email' => 'j.tester@northwind.example', 'full_name' => 'Jo Tester']))
        ->not->toContain('name.placeholder')
        ->not->toContain('email.placeholder');
});

it('flags a mailbox name that does not read like a name', function (): void {
    expect(checks([
        'email' => 'xkcdfghjklmnp@northwind.example',
        'full_name' => 'Amara Okafor',
    ]))->toContain('email.gibberish');
});

it('leaves ordinary short mailbox names alone', function (): void {
    // Conservative on purpose: flagging a real person costs more than missing
    // a bot, so initials and short handles must pass.
    foreach (['jo', 'ao', 'a.okafor', 'amara', 'mkt2026'] as $local) {
        expect(checks([
            'email' => "{$local}@northwind.example",
            'full_name' => 'Amara Okafor',
        ]))->not->toContain('email.gibberish');
    }
});

// --- Role and free mailboxes ------------------------------------------------

it('warns about a shared inbox without discarding it', function (): void {
    $result = verify([
        'email' => 'info@northwind.example',
        'full_name' => 'Northwind Trading',
    ]);

    // info@ is how plenty of small businesses genuinely reply.
    expect($result->status)->toBe(VerificationStatus::Valid)
        ->and(array_column($result->findings, 'check'))->toContain('email.role')
        ->and($result->status->isWorkable())->toBeTrue();
});

it('notes a personal mailbox as a small deduction only', function (): void {
    $result = verify([
        'email' => 'amara.okafor@gmail.com',
        'full_name' => 'Amara Okafor',
    ]);

    // It matters a lot for enterprise sales and not at all for B2C, so it is a
    // visible reason rather than a judgement.
    expect($result->status)->toBe(VerificationStatus::Valid)
        ->and($result->confidence)->toBe(95)
        ->and(array_column($result->findings, 'check'))->toContain('email.free_provider');
});

// --- Phones -----------------------------------------------------------------

it('rejects a number that cannot be dialled', function (): void {
    // Judged against the country's numbering plan rather than a digit count,
    // which is why the second one fails despite a valid UAE prefix. The
    // country-by-country cases live in PhoneValidationTest.
    expect(checks(['phone' => '+971 50 123', 'full_name' => 'Amara Okafor']))
        ->toContain('phone.invalid_for_country')
        // Carries a country code, so it parses and is then judged too long for
        // the plan — invalid rather than unparseable.
        ->and(checks(['phone' => '+9715012345678901234', 'full_name' => 'Amara Okafor']))
        ->toContain('phone.invalid_for_country');
});

it('rejects filler digits', function (): void {
    expect(checks(['phone' => '+1 999 999 9999', 'full_name' => 'Amara Okafor']))
        ->toContain('phone.invalid_for_country')
        ->and(checks(['phone' => '+1 123 456 7890', 'full_name' => 'Amara Okafor']))
        ->toContain('phone.invalid_for_country');
});

it('accepts a real number however it was typed', function (): void {
    foreach (['+971 50 123 4567', '+971501234567', '00971501234567', '+971-50-123-4567'] as $phone) {
        expect(checks(['phone' => $phone, 'full_name' => 'Amara Okafor']))
            ->not->toContain('phone.invalid_for_country')
            ->not->toContain('phone.unparseable');
    }
});

// --- Consent ----------------------------------------------------------------

it('notes a missing agreement to be contacted', function (): void {
    $result = verify([
        'email' => 'amara@northwind.example',
        'full_name' => 'Amara Okafor',
        'consent' => false,
    ]);

    // Not about whether the details are real, but about whether they can be
    // used, which is the same question from the rep's point of view (§88).
    expect(array_column($result->findings, 'check'))->toContain('consent.absent')
        ->and($result->confidence)->toBe(85);
});

// --- Explainability ---------------------------------------------------------

it('explains every verdict in words a rep can act on', function (): void {
    $result = verify(['email' => 'test@mailinator.com', 'full_name' => 'John Doe']);

    foreach ($result->findings as $finding) {
        expect($finding['detail'])->not->toBeEmpty()
            ->and($finding['verdict'])->toBeIn(['fail', 'warn', 'pass']);
    }

    // §59. "Not usable" with no reason cannot be corrected, and a flag nobody
    // can explain is a flag nobody trusts.
    expect($result->problems())->not->toBeEmpty();
});

// --- Persisting -------------------------------------------------------------

it('stores the verdict, the confidence and the reasons', function (): void {
    $lead = Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'email' => 'someone@mailinator.com',
    ]);

    $this->verifier->apply($lead, checkMx: false);

    $lead->refresh();

    expect($lead->verification_status)->toBe(VerificationStatus::Risky)
        ->and($lead->verification_confidence)->toBeLessThan(100)
        ->and($lead->verification_findings)->not->toBeEmpty()
        ->and($lead->verified_at)->not->toBeNull();
});

it('starts unverified rather than pretending to be valid', function (): void {
    $lead = Lead::factory()->create(['tenant_id' => $this->tenant->id]);

    // "We have not checked" and "we checked and it is fine" are different
    // facts, and a rep needs to know which.
    expect($lead->verification_status)->toBe(VerificationStatus::Unverified)
        ->and($lead->verified_at)->toBeNull();
});

// --- The scope the feature exists for ---------------------------------------

it('excludes only the unreachable from the workable scope', function (): void {
    foreach (VerificationStatus::cases() as $status) {
        Lead::factory()->create([
            'tenant_id' => $this->tenant->id,
            'verification_status' => $status,
        ]);
    }

    $workable = Lead::query()->workable()->pluck('verification_status');

    // Risky is kept: flagging a role address is a warning, not a verdict.
    expect($workable)->toHaveCount(3)
        ->and($workable->pluck('value')->all())
        ->toEqualCanonicalizing(['unverified', 'valid', 'risky']);
});

// --- Thresholds are the workspace's call ------------------------------------

it('respects retuned thresholds', function (): void {
    config(['verification.thresholds' => ['valid' => 96, 'risky' => 10]]);

    // The same lead, a stricter workspace: a free mailbox now needs a look.
    expect(verify([
        'email' => 'amara@gmail.com',
        'full_name' => 'Amara Okafor',
    ])->status)->toBe(VerificationStatus::Risky);
});
