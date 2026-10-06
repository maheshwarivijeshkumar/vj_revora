<?php

declare(strict_types=1);

use App\Domain\Contact\PhoneNumber;
use App\Domain\Leads\Enums\VerificationStatus;
use App\Domain\Leads\Services\LeadVerifier;
use App\Domain\Tenancy\TenantContext;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Phone validation, every country
|--------------------------------------------------------------------------
|
| §17. Backed by Google's libphonenumber metadata, so "is this a real number in
| this country" is answered from each country's numbering plan rather than by
| counting digits.
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    Queue::fake();
});

function phoneChecks(string $phone, ?string $country = null): array
{
    $lead = Lead::factory()->make([
        'tenant_id' => test()->tenant->id,
        'email' => 'amara@northwind.example',
        'full_name' => 'Amara Okafor',
        'consent' => true,
        'phone' => $phone,
        'country' => $country,
    ]);

    return array_column(
        app(LeadVerifier::class)->verify($lead, checkMx: false)->findings,
        'check',
    );
}

// --- Valid mobiles the world over -------------------------------------------

it('accepts a real mobile from every region', function (string $phone, string $region): void {
    $number = PhoneNumber::parse($phone);

    expect($number)->not->toBeNull()
        ->and($number->isValid)->toBeTrue()
        ->and($number->region)->toBe($region)
        ->and($number->isMobile())->toBeTrue();
})->with([
    'UAE' => ['+971501234567', 'AE'],
    'Saudi Arabia' => ['+966512345678', 'SA'],
    'India' => ['+919876543210', 'IN'],
    'Pakistan' => ['+923001234567', 'PK'],
    'United States' => ['+12125551234', 'US'],
    'Brazil' => ['+5511987654321', 'BR'],
    'Germany' => ['+4915112345678', 'DE'],
    'France' => ['+33612345678', 'FR'],
    'Spain' => ['+34612345678', 'ES'],
    'Italy' => ['+393123456789', 'IT'],
    'Netherlands' => ['+31612345678', 'NL'],
    'Nigeria' => ['+2348031234567', 'NG'],
    'Kenya' => ['+254712345678', 'KE'],
    'South Africa' => ['+27821234567', 'ZA'],
    'Egypt' => ['+201012345678', 'EG'],
    'China' => ['+8613800138000', 'CN'],
    'Japan' => ['+819012345678', 'JP'],
    'Indonesia' => ['+6281234567890', 'ID'],
    'Australia' => ['+61412345678', 'AU'],
    'Singapore' => ['+6581234567', 'SG'],
    'Turkey' => ['+905301234567', 'TR'],
    'Mexico' => ['+525512345678', 'MX'],
]);

it('reduces the same number written four ways to one key', function (): void {
    $forms = [
        '+971501234567',
        '+971 50 123 4567',
        '00971501234567',
        '+971-50-123-4567',
    ];

    $keys = array_map(fn (string $form): ?string => PhoneNumber::normalise($form), $forms);

    expect(array_unique($keys))->toHaveCount(1)
        ->and($keys[0])->toBe('+971501234567');
});

// --- Numbers written without a country code ---------------------------------

it('uses the lead country to parse a national number', function (): void {
    // `050 123 4567` is a different number in every country, so it is only
    // parseable against one.
    expect(PhoneNumber::normalise('050 123 4567', 'AE'))->toBe('+971501234567')
        ->and(PhoneNumber::normalise('09876543210', 'IN'))->toBe('+919876543210');
});

it('leaves a national number unparseable when no country is known', function (): void {
    config(['verification.default_region' => null]);

    // Guessing would silently mangle it. Unparseable is the honest answer.
    expect(PhoneNumber::normalise('050 123 4567'))->toBeNull();
});

it('falls back to the configured default region', function (): void {
    config(['verification.default_region' => 'AE']);

    expect(PhoneNumber::normalise('050 123 4567'))->toBe('+971501234567');
});

it('prefers the record country over the configured default', function (): void {
    config(['verification.default_region' => 'AE']);

    // The record is better evidence than a workspace-wide fallback.
    expect(PhoneNumber::normalise('09876543210', 'IN'))->toBe('+919876543210');
});

// --- Country-specific invalidity --------------------------------------------

it('rejects a number that is the wrong length for its own country', function (): void {
    // Twelve digits with a valid UAE prefix, and still not a UAE number — the
    // kind of thing only a numbering plan can tell you.
    expect(phoneChecks('+97141234567'))->toContain('phone.invalid_for_country');
});

it('rejects an unassigned country code', function (): void {
    // 999 belongs to nobody, so there is no plan to judge it against and the
    // number is unparseable rather than invalid. Both are failures; the
    // distinction is what the message can usefully say.
    expect(phoneChecks('+99912345678'))->toContain('phone.unparseable');
});

it('rejects filler digits', function (): void {
    foreach (['1234567890', '9999999999', '123'] as $filler) {
        expect(phoneChecks($filler, 'US'))->toContain('phone.invalid_for_country');
    }

    // All zeroes has no country code to find, so it does not parse at all.
    expect(phoneChecks('0000000000', 'US'))->toContain('phone.unparseable');
});

it('says which country the number was judged against', function (): void {
    $lead = Lead::factory()->make([
        'tenant_id' => $this->tenant->id,
        'email' => 'amara@northwind.example',
        'full_name' => 'Amara Okafor',
        'phone' => '+97141234567',
    ]);

    $findings = app(LeadVerifier::class)->verify($lead, checkMx: false)->findings;
    $detail = collect($findings)->firstWhere('check', 'phone.invalid_for_country')['detail'];

    // The usual cause is a number parsed against the wrong country, and the
    // rep can only fix that if they are told which one (§59).
    expect($detail)->toContain('AE');
});

// --- Line type ---------------------------------------------------------------

it('tells a mobile from a landline', function (): void {
    expect(PhoneNumber::parse('+971501234567')->isMobile())->toBeTrue()
        ->and(PhoneNumber::parse('+97143123456')->isMobile())->toBeFalse()
        ->and(PhoneNumber::parse('+97143123456')->typeLabel())->toBe('Landline');
});

it('treats a North American number as possibly mobile', function (): void {
    $number = PhoneNumber::parse('+12125551234');

    // The NANP does not distinguish the two, so insisting on a definite MOBILE
    // would reject every American mobile there is.
    expect($number->isMobile())->toBeTrue()
        ->and($number->typeCode())->toBe('fixed_or_mobile');
});

it('warns about a landline without failing it', function (): void {
    $checks = phoneChecks('+97143123456');

    // Whole industries answer their landline; it just cannot be texted.
    expect($checks)->toContain('phone.not_mobile')
        ->and($checks)->not->toContain('phone.invalid_for_country');
});

it('a landline lead is still verified', function (): void {
    $lead = Lead::factory()->make([
        'tenant_id' => $this->tenant->id,
        'email' => 'amara@northwind.example',
        'full_name' => 'Amara Okafor',
        'consent' => true,
        'phone' => '+97143123456',
    ]);

    expect(app(LeadVerifier::class)->verify($lead, checkMx: false)->status)
        ->toBe(VerificationStatus::Valid);
});

it('flags a premium-rate number', function (): void {
    // Not how somebody asks to be contacted, and a common filler value.
    expect(phoneChecks('+449012345678'))->toContain('phone.suspicious_type');
});

// --- Stored metadata ---------------------------------------------------------

it('records the line type and country on the lead', function (): void {
    $lead = Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'phone' => '+971501234567',
        'email' => 'amara@northwind.example',
    ]);

    app(LeadVerifier::class)->apply($lead, checkMx: false);

    $lead->refresh();

    // Stored because "can this lead be texted" decides which channel reaches
    // them, and parsing on every send would put the metadata on a hot path.
    expect($lead->phone_type)->toBe('mobile')
        ->and($lead->phone_country)->toBe('AE');
});

it('clears the metadata when the number becomes unparseable', function (): void {
    $lead = Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'phone' => '+971501234567',
        'email' => 'amara@northwind.example',
    ]);

    app(LeadVerifier::class)->apply($lead, checkMx: false);
    $lead->forceFill(['phone' => 'call reception'])->save();
    app(LeadVerifier::class)->apply($lead, checkMx: false);

    expect($lead->refresh()->phone_type)->toBeNull()
        ->and($lead->phone_country)->toBeNull();
});

// --- Deduplication, which is the point --------------------------------------

it('matches a lead entered nationally against the same one entered with a code', function (): void {
    Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'phone' => '+971501234567',
        'country' => 'AE',
        'email' => null,
    ]);

    $second = Lead::factory()->create([
        'tenant_id' => $this->tenant->id,
        'phone' => '050 123 4567',
        'country' => 'AE',
        'email' => null,
    ]);

    // The old digits-only key made these two different people, so the same
    // person entered both ways stayed two leads (§18).
    expect($second->phone_normalized)->toBe('+971501234567');
});

it('normalises a contact number the same way', function (): void {
    $contact = Contact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'phone' => '050 123 4567',
        'country' => 'AE',
    ]);

    expect($contact->phone_normalized)->toBe('+971501234567');
});

// --- Not a phone number at all ----------------------------------------------

it('returns nothing for text that is not a number', function (): void {
    foreach (['', '   ', 'call me', 'n/a', 'see email'] as $input) {
        expect(PhoneNumber::parse($input))->toBeNull()
            ->and(PhoneNumber::normalise($input))->toBeNull();
    }
});

it('asks for a country code when it cannot parse at all', function (): void {
    config(['verification.default_region' => null]);

    $lead = Lead::factory()->make([
        'tenant_id' => $this->tenant->id,
        'email' => 'amara@northwind.example',
        'full_name' => 'Amara Okafor',
        'phone' => '050 123 4567',
    ]);

    $findings = app(LeadVerifier::class)->verify($lead, checkMx: false)->findings;
    $detail = collect($findings)->firstWhere('check', 'phone.unparseable')['detail'];

    expect($detail)->toContain('+971');
});
