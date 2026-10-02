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
| Company endpoints
|--------------------------------------------------------------------------
|
| §47. Matched on domain first and exact name second, because two Acmes on the
| board is a data problem no reporting can recover from (§18).
|
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);

    $this->token = ApiKey::mint('Test', ['companies.write'])['token'];
});

function companyApi(string $method, string $path, array $body = []): TestResponse
{
    return test()
        ->withHeader('Authorization', 'Bearer '.test()->token)
        ->json($method, $path, $body);
}

// --- Creating ---------------------------------------------------------------

it('creates a company and normalises its domain', function (): void {
    $response = companyApi('POST', '/api/v1/companies', [
        'name' => 'Acme Industries',
        'website' => 'https://www.Acme.Example/pricing?ref=x',
        'industry' => 'Logistics',
    ])->assertCreated();

    expect($response->json('data.name'))->toBe('Acme Industries')
        // The stored host is what matching compares, so it has to be stable.
        ->and($response->json('data.domain'))->toBe('acme.example')
        ->and($response->json('data.website'))->toBe('https://www.Acme.Example/pricing?ref=x')
        ->and($response->json('data.id'))->toBe(Company::first()->uuid)
        ->and($response->json('meta.is_duplicate'))->toBeFalse();
});

it('requires a name', function (): void {
    companyApi('POST', '/api/v1/companies', ['domain' => 'acme.example'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('returns 200 and enriches when the domain already exists', function (): void {
    companyApi('POST', '/api/v1/companies', [
        'name' => 'Acme',
        'domain' => 'acme.example',
    ])->assertCreated();

    $second = companyApi('POST', '/api/v1/companies', [
        // A different trading name on the same domain is the same company.
        'name' => 'Acme Industries Ltd',
        'website' => 'http://acme.example',
        'industry' => 'Logistics',
    ])->assertOk();

    expect($second->json('meta.is_duplicate'))->toBeTrue()
        ->and(Company::count())->toBe(1)
        // The gap was filled; the name on file was not overwritten by a later
        // payload that is not automatically more correct.
        ->and($second->json('data.name'))->toBe('Acme')
        ->and($second->json('data.industry'))->toBe('Logistics');
});

it('matches on an exact name when there is no domain', function (): void {
    companyApi('POST', '/api/v1/companies', ['name' => 'Acme'])->assertCreated();

    companyApi('POST', '/api/v1/companies', ['name' => 'acme'])->assertOk();

    expect(Company::count())->toBe(1);
});

it('treats a longer name as a different company', function (): void {
    companyApi('POST', '/api/v1/companies', ['name' => 'Acme'])->assertCreated();

    // "Acme" and "Acme Holdings" are routinely different companies, and
    // merging them is not something a human can undo later.
    companyApi('POST', '/api/v1/companies', ['name' => 'Acme Holdings'])->assertCreated();

    expect(Company::count())->toBe(2);
});

// --- Reading ----------------------------------------------------------------

it('lists companies with their contact and deal counts', function (): void {
    $company = Company::factory()->create(['tenant_id' => $this->tenant->id]);
    $contact = Contact::factory()->create(['tenant_id' => $this->tenant->id]);
    $company->contacts()->attach($contact->id);

    $response = companyApi('GET', '/api/v1/companies')->assertOk();

    expect($response->json('meta.total'))->toBe(1)
        // Counted rather than embedded: a caller listing companies wants the
        // size, not thousands of people per row.
        ->and($response->json('data.0.contacts_count'))->toBe(1)
        ->and($response->json('data.0.deals_count'))->toBe(0);
});

it('caps per_page so a caller cannot ask for everything', function (): void {
    Company::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

    expect(companyApi('GET', '/api/v1/companies?per_page=5000')->json('meta.per_page'))->toBe(100);
});

it('finds a company by a full url as well as a bare domain', function (): void {
    Company::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Acme',
        'domain' => 'acme.example',
    ]);

    expect(companyApi('GET', '/api/v1/companies?domain=acme.example')->json('meta.total'))->toBe(1)
        ->and(companyApi('GET', '/api/v1/companies?domain='.urlencode('https://www.acme.example/x'))->json('meta.total'))->toBe(1);
});

it('searches by name', function (): void {
    Company::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Northwind Trading']);
    Company::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Acme']);

    expect(companyApi('GET', '/api/v1/companies?search=Northwind')->json('meta.total'))->toBe(1);
});

it('shows only this workspace\'s companies', function (): void {
    Company::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

    $other = Tenant::factory()->create();
    Company::factory()->count(4)->create(['tenant_id' => $other->id]);

    expect(companyApi('GET', '/api/v1/companies')->json('meta.total'))->toBe(2);
});

it('lists the people at a company, current employer first', function (): void {
    // Reading people needs the contacts scope; companies.write does not cover it.
    $this->token = ApiKey::mint('Reader', ['companies.read', 'contacts.read'])['token'];

    $company = Company::factory()->create(['tenant_id' => $this->tenant->id]);

    $junior = Contact::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Junior']);
    $primary = Contact::factory()->create(['tenant_id' => $this->tenant->id, 'first_name' => 'Primary']);

    $company->contacts()->attach($junior->id, ['is_primary' => false]);
    $company->contacts()->attach($primary->id, ['is_primary' => true]);

    $response = companyApi('GET', "/api/v1/companies/{$company->uuid}/contacts")->assertOk();

    expect($response->json('meta.total'))->toBe(2)
        ->and($response->json('data.0.first_name'))->toBe('Primary');
});

it('returns 404 for an unknown uuid', function (): void {
    companyApi('GET', '/api/v1/companies/01932000-0000-7000-8000-000000000000')->assertNotFound();
});

// --- Updating ---------------------------------------------------------------

it('updates editable fields and renormalises the domain', function (): void {
    $company = Company::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = companyApi('PATCH', "/api/v1/companies/{$company->uuid}", [
        'name' => 'Renamed',
        'website' => 'https://www.renamed.example',
    ])->assertOk();

    expect($response->json('data.name'))->toBe('Renamed')
        ->and($response->json('data.domain'))->toBe('renamed.example');
});

// --- Deleting ---------------------------------------------------------------

it('soft deletes a company', function (): void {
    $company = Company::factory()->create(['tenant_id' => $this->tenant->id]);

    companyApi('DELETE', "/api/v1/companies/{$company->uuid}")->assertNoContent();

    expect(Company::count())->toBe(0)
        ->and(Company::withTrashed()->count())->toBe(1);
});

// --- Scopes -----------------------------------------------------------------

it('grants read access to a write key without asking for both', function (): void {
    // companies.write implies companies.read: an integration that can create a
    // company can obviously see the one it just created.
    companyApi('GET', '/api/v1/companies')->assertOk();
});

it('refuses listing a company\'s people without the contacts scope', function (): void {
    $company = Company::factory()->create(['tenant_id' => $this->tenant->id]);

    // companies.write says nothing about reading people, and the people are
    // what carries the personal data.
    $response = companyApi('GET', "/api/v1/companies/{$company->uuid}/contacts")
        ->assertForbidden();

    expect($response->json('required_scope'))->toBe('contacts.read');
});
