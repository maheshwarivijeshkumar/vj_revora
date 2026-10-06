<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Domain\Webhooks\Enums\WebhookEvent;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Backfilling phone keys
|--------------------------------------------------------------------------
|
| The normaliser changed from digits-only to E.164, so rows written by the old
| one hold keys that no longer match what a new row produces — and deduplication
| silently misses them until this has run.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    Queue::fake();
});

/**
 * Writes a row the way the old digits-only normaliser would have.
 */
function staleLead(string $phone, string $oldKey, ?string $country = 'AE'): Lead
{
    $lead = Lead::factory()->create([
        'tenant_id' => test()->tenant->id,
        'phone' => $phone,
        'country' => $country,
    ]);

    // Straight to the database, bypassing the model's saving hook, which is the
    // only way to reproduce a pre-migration row.
    Lead::query()->whereKey($lead->id)->update([
        'phone_normalized' => $oldKey,
        'phone_type' => null,
        'phone_country' => null,
    ]);

    return $lead->refresh();
}

it('rewrites a stale key to E.164', function (): void {
    $lead = staleLead('050 123 4567', '0501234567');

    $this->artisan('leads:renormalise-phones')->assertSuccessful();

    $lead->refresh();

    expect($lead->phone_normalized)->toBe('+971501234567')
        ->and($lead->phone_type)->toBe('mobile')
        ->and($lead->phone_country)->toBe('AE');
});

it('reports without writing on a dry run', function (): void {
    $lead = staleLead('050 123 4567', '0501234567');

    $this->artisan('leads:renormalise-phones --dry-run')
        ->expectsOutputToContain('would change')
        ->assertSuccessful();

    expect($lead->refresh()->phone_normalized)->toBe('0501234567');
});

it('is safe to run twice', function (): void {
    staleLead('050 123 4567', '0501234567');

    $this->artisan('leads:renormalise-phones');

    // Second pass finds nothing left to do, which is what makes it safe to
    // schedule or re-run after a failure. Asserted on the data rather than the
    // wording, so a reworded summary is not a failing test.
    $this->artisan('leads:renormalise-phones')->assertSuccessful();

    expect(Lead::query()->where('phone_normalized', '+971501234567')->count())->toBe(1);
});

it('leaves a number it cannot parse with a null key rather than a wrong one', function (): void {
    $lead = staleLead('call reception', 'callreception', null);

    $this->artisan('leads:renormalise-phones');

    expect($lead->refresh()->phone_normalized)->toBeNull();
});

it('normalises contacts too', function (): void {
    $contact = Contact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'phone' => '050 123 4567',
        'country' => 'AE',
    ]);

    Contact::query()->whereKey($contact->id)->update(['phone_normalized' => '0501234567']);

    $this->artisan('leads:renormalise-phones');

    expect($contact->refresh()->phone_normalized)->toBe('+971501234567');
});

it('announces nothing, because a normalisation is not an edit', function (): void {
    WebhookEndpoint::factory()
        ->subscribedTo([WebhookEvent::LeadUpdated])
        ->create(['tenant_id' => $this->tenant->id]);

    staleLead('050 123 4567', '0501234567');

    $before = AuditLog::count();

    $this->artisan('leads:renormalise-phones');

    // A webhook and an audit row per record would make a migration look like a
    // thousand people editing the database at once (§49, §54).
    expect(WebhookDelivery::query()->where('event', 'lead.updated')->count())->toBe(0)
        ->and(AuditLog::count())->toBe($before);
});

it('crosses every workspace unless told otherwise', function (): void {
    staleLead('050 123 4567', '0501234567');

    $other = Tenant::factory()->create();

    $theirs = app(TenantContext::class)->runAs($other, function () use ($other): Lead {
        $lead = Lead::factory()->create([
            'tenant_id' => $other->id,
            'phone' => '050 765 4321',
            'country' => 'AE',
        ]);

        Lead::query()->whereKey($lead->id)->update(['phone_normalized' => '0507654321']);

        return $lead;
    });

    $this->artisan('leads:renormalise-phones')->assertSuccessful();

    expect($theirs->refresh()->phone_normalized)->toBe('+971507654321');
});

it('can be limited to one workspace', function (): void {
    $mine = staleLead('050 123 4567', '0501234567');

    $other = Tenant::factory()->create();

    $theirs = app(TenantContext::class)->runAs($other, function () use ($other): Lead {
        $lead = Lead::factory()->create([
            'tenant_id' => $other->id,
            'phone' => '050 765 4321',
            'country' => 'AE',
        ]);

        Lead::query()->whereKey($lead->id)->update(['phone_normalized' => '0507654321']);

        return $lead;
    });

    $this->artisan("leads:renormalise-phones --tenant={$this->tenant->id}")->assertSuccessful();

    // So a large estate can be migrated workspace by workspace rather than in
    // one long run nobody can interrupt.
    expect($mine->refresh()->phone_normalized)->toBe('+971501234567')
        ->and($theirs->refresh()->phone_normalized)->toBe('0507654321');
});
