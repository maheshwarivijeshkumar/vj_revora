<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Models\ApiKey;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tenant;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| Contact endpoints
|--------------------------------------------------------------------------
|
| §47. Writes run through the same upsert the rest of the application uses, so
| an integration gets the same identifier matching as a form fill (§18).
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);

    $this->token = ApiKey::mint('Test', ['contacts.write', 'companies.write'])['token'];
});

function contactApi(string $method, string $path, array $body = []): TestResponse
{
    return test()
        ->withHeader('Authorization', 'Bearer '.test()->token)
        ->json($method, $path, $body);
}

// --- Creating ---------------------------------------------------------------

it('creates a contact and composes the full name', function (): void {
    $response = contactApi('POST', '/api/v1/contacts', [
        'first_name' => 'Amara',
        'last_name' => 'Okafor',
        'email' => 'amara@acme.example',
        'job_title' => 'Head of Growth',
    ])->assertCreated();

    expect($response->json('data.full_name'))->toBe('Amara Okafor')
        // The public identifier is the uuid; internal ids stay internal.
        ->and($response->json('data.id'))->toBe(Contact::first()->uuid)
        ->and($response->json('data'))->not->toHaveKey('tenant_id')
        ->and($response->json('meta.is_duplicate'))->toBeFalse();
});

it('refuses a contact with nothing to identify them by', function (): void {
    contactApi('POST', '/api/v1/contacts', ['first_name' => 'Nobody'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect(Contact::count())->toBe(0);
});

it('accepts a contact identified only by phone', function (): void {
    contactApi('POST', '/api/v1/contacts', ['phone' => '+971501234567'])->assertCreated();
});

it('returns 200 and enriches rather than creating a second person', function (): void {
    contactApi('POST', '/api/v1/contacts', [
        'email' => 'dupe@acme.example',
        'first_name' => 'Sam',
    ])->assertCreated();

    $second = contactApi('POST', '/api/v1/contacts', [
        // Matched on the normalised column, so case cannot hide it.
        'email' => 'DUPE@acme.example',
        'job_title' => 'CTO',
    ])->assertOk();

    // 201 would tell a retrying client it had made a second person.
    expect($second->json('meta.is_duplicate'))->toBeTrue()
        ->and(Contact::count())->toBe(1)
        ->and($second->json('data.job_title'))->toBe('CTO')
        // The gap was filled; the name already on file was not overwritten.
        ->and($second->json('data.first_name'))->toBe('Sam');
});

it('matches an existing person on a reformatted phone number', function (): void {
    contactApi('POST', '/api/v1/contacts', ['phone' => '+971 50 123 4567']);

    contactApi('POST', '/api/v1/contacts', ['phone' => '+971501234567'])->assertOk();

    expect(Contact::count())->toBe(1);
});

// --- Company resolution -----------------------------------------------------

it('creates the named company and makes it primary', function (): void {
    $response = contactApi('POST', '/api/v1/contacts', [
        'email' => 'kai@acme.example',
        'company' => 'Acme Industries',
        'company_domain' => 'https://www.acme.example/about',
    ])->assertCreated();

    expect($response->json('data.companies.0.name'))->toBe('Acme Industries')
        // Reduced to a comparable host, so matching works however it was typed.
        ->and($response->json('data.companies.0.domain'))->toBe('acme.example')
        ->and($response->json('data.companies.0.is_primary'))->toBeTrue();
});

it('attaches to an existing company by email domain', function (): void {
    $company = Company::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Acme',
        'domain' => 'acme.example',
    ]);

    $response = contactApi('POST', '/api/v1/contacts', ['email' => 'nia@acme.example'])
        ->assertCreated();

    expect($response->json('data.companies'))->toHaveCount(1)
        ->and($response->json('data.companies.0.id'))->toBe($company->uuid);
});

it('does not invent a company from an email domain', function (): void {
    contactApi('POST', '/api/v1/contacts', ['email' => 'solo@unknown.example'])->assertCreated();

    // A host name is not a company name, and a fabricated organisation is a
    // record nobody can tidy up.
    expect(Company::count())->toBe(0);
});

it('never groups people by their personal mailbox provider', function (): void {
    Company::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Gmail',
        'domain' => 'gmail.com',
    ]);

    $response = contactApi('POST', '/api/v1/contacts', ['email' => 'someone@gmail.com'])
        ->assertCreated();

    // Everyone on gmail.com does not work at the same place.
    expect($response->json('data.companies'))->toBeEmpty();
});

it('links an existing contact to a company and promotes one primary', function (): void {
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);
    $first = Company::factory()->create(['tenant_id' => $this->tenant->id]);
    $second = Company::factory()->create(['tenant_id' => $this->tenant->id]);

    contactApi('POST', "/api/v1/contacts/{$contact->uuid}/companies", [
        'company_id' => $first->uuid,
        'role' => 'Founder',
        'is_primary' => true,
    ])->assertOk();

    $response = contactApi('POST', "/api/v1/contacts/{$contact->uuid}/companies", [
        'company_id' => $second->uuid,
        'is_primary' => true,
    ])->assertOk();

    $primaries = collect($response->json('data.companies'))->where('is_primary', true);

    // Exactly one employer can be current.
    expect($primaries)->toHaveCount(1)
        ->and($primaries->first()['id'])->toBe($second->uuid);
});

it('unlinks a contact from a company', function (): void {
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);
    $company = Company::factory()->create(['tenant_id' => $this->tenant->id]);

    contactApi('POST', "/api/v1/contacts/{$contact->uuid}/companies", [
        'company_id' => $company->uuid,
    ])->assertOk();

    contactApi('DELETE', "/api/v1/contacts/{$contact->uuid}/companies/{$company->uuid}")
        ->assertNoContent();

    expect($contact->companies()->count())->toBe(0);
});

it('refuses a company from another workspace', function (): void {
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);
    $other = Tenant::factory()->create();

    $foreign = app(TenantContext::class)->runAs(
        $other,
        fn () => Company::factory()->create(['tenant_id' => $other->id]),
    );

    contactApi('POST', "/api/v1/contacts/{$contact->uuid}/companies", [
        'company_id' => $foreign->uuid,
    ])->assertNotFound();
});

// --- Reading ----------------------------------------------------------------

it('lists contacts with pagination metadata', function (): void {
    Contact::factory()->count(30)->create(['tenant_id' => $this->tenant->id]);

    $response = contactApi('GET', '/api/v1/contacts?per_page=10')->assertOk();

    expect($response->json('data'))->toHaveCount(10)
        ->and($response->json('meta.total'))->toBe(30);
});

it('caps per_page so a caller cannot ask for everything', function (): void {
    Contact::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

    expect(contactApi('GET', '/api/v1/contacts?per_page=5000')->json('meta.per_page'))->toBe(100);
});

it('filters by email, name and company', function (): void {
    $company = Company::factory()->create(['tenant_id' => $this->tenant->id]);

    $found = Contact::factory()->create([
        'tenant_id' => $this->tenant->id,
        'first_name' => 'Zephyr',
        'last_name' => 'Quill',
        'email' => 'find@acme.example',
    ]);
    $found->companies()->attach($company->id, ['is_primary' => true]);

    Contact::factory()->create(['tenant_id' => $this->tenant->id, 'email' => 'other@acme.example']);

    expect(contactApi('GET', '/api/v1/contacts?email=FIND@acme.example')->json('meta.total'))->toBe(1)
        ->and(contactApi('GET', '/api/v1/contacts?search=Zephyr')->json('meta.total'))->toBe(1)
        // By public uuid, because that is the only company id this API hands out.
        ->and(contactApi('GET', "/api/v1/contacts?company_id={$company->uuid}")->json('meta.total'))->toBe(1);
});

it('rejects an unparseable updated_since rather than ignoring it', function (): void {
    // Silently returning everything would look like the filter had worked.
    contactApi('GET', '/api/v1/contacts?updated_since=last-tuesday')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('updated_since');
});

it('shows only this workspace\'s contacts', function (): void {
    Contact::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    $other = Tenant::factory()->create();
    Contact::factory()->count(5)->create(['tenant_id' => $other->id]);

    expect(contactApi('GET', '/api/v1/contacts')->json('meta.total'))->toBe(2);
});

// --- Updating ---------------------------------------------------------------

it('updates editable fields', function (): void {
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    contactApi('PATCH', "/api/v1/contacts/{$contact->uuid}", ['job_title' => 'CFO'])
        ->assertOk()
        ->assertJsonPath('data.job_title', 'CFO');
});

it('refuses a company change through update and says where to go', function (): void {
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = contactApi('PATCH', "/api/v1/contacts/{$contact->uuid}", ['company' => 'Acme'])
        ->assertUnprocessable();

    expect($response->json('errors.company.0'))->toContain('/companies');
});

// --- Deleting ---------------------------------------------------------------

it('soft deletes a contact', function (): void {
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);

    contactApi('DELETE', "/api/v1/contacts/{$contact->uuid}")->assertNoContent();

    expect(Contact::count())->toBe(0)
        ->and(Contact::withTrashed()->count())->toBe(1);
});

// --- Scopes -----------------------------------------------------------------

it('refuses a write with a read-only key', function (): void {
    $readOnly = ApiKey::mint('Reader', ['contacts.read'])['token'];

    $response = test()
        ->withHeader('Authorization', 'Bearer '.$readOnly)
        ->postJson('/api/v1/contacts', ['email' => 'nope@acme.example'])
        ->assertForbidden();

    // The 403 names the scope, so a developer can fix it without guessing.
    expect($response->json('required_scope'))->toBe('contacts.write');
});
