<?php

declare(strict_types=1);

use App\Domain\Tenancy\TenantContext;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Find businesses by city and add them as leads
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($this->tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->tenant->id);

    foreach (['lead.view', 'lead.create'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::findOrCreate('Owner', 'web');
    $role->syncPermissions(['lead.view', 'lead.create']);

    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole($role);

    config([
        'verification.queue_recheck' => false,
        'services.google.places_key' => 'test-places-key',
    ]);
});

it('finds businesses for a city and adds the chosen ones as leads', function (): void {
    Http::fake([
        'places.googleapis.com/*' => Http::response([
            'places' => [[
                'id' => 'ChIJ-cafe',
                'businessStatus' => 'OPERATIONAL',
                'displayName' => ['text' => 'Cafe North'],
                'formattedAddress' => '12 Mall Road, Lahore',
                'internationalPhoneNumber' => '+92 42 111 222 333',
                'websiteUri' => 'https://cafenorth.example',
                'googleMapsUri' => 'https://maps.google.com/?cid=1',
                'addressComponents' => [
                    ['longText' => 'Lahore', 'shortText' => 'Lahore', 'types' => ['locality']],
                    ['longText' => 'Punjab', 'shortText' => 'Punjab', 'types' => ['administrative_area_level_1']],
                    ['longText' => 'Pakistan', 'shortText' => 'PK', 'types' => ['country']],
                ],
            ]],
        ]),
        'nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => '31.5204', 'lon' => '74.3587'],
        ]),
        'overpass-api.de/*' => Http::response([
            'elements' => [[
                'type' => 'node',
                'id' => 99,
                'tags' => [
                    'name' => 'Starbucks Lahore',
                    'phone' => '+92 42 000 0000',
                    'addr:city' => 'Lahore',
                    'addr:state' => 'Punjab',
                    'addr:country' => 'PK',
                ],
            ]],
        ]),
    ]);
    Http::preventStrayRequests();

    $search = $this->actingAs($this->user)->post('/discovery/search', [
        'city' => 'Lahore',
        'state' => 'Punjab',
        'country' => 'Pakistan',
        'category' => 'cafe',
        'source' => 'both',
    ])->assertRedirect();

    parse_str((string) parse_url($search->headers->get('Location'), PHP_URL_QUERY), $query);
    $cached = Cache::get('discovery.'.$this->tenant->id.'.'.$query['search']);

    expect($cached['businesses'])->toHaveCount(2)
        ->and(collect($cached['businesses'])->pluck('source_name')->sort()->values()->all())
        ->toBe(['Business Directory', 'Google Maps'])
        ->and(collect($cached['businesses'])->firstWhere('name', 'Starbucks Lahore')['likely_chain'])->toBeTrue();

    $this->actingAs($this->user)->post('/discovery/import', [
        'search' => $query['search'],
        'ids' => ['ChIJ-cafe', 'node/99'],
    ])->assertRedirect();

    $cafe = Lead::query()->where('external_record_id', 'ChIJ-cafe')->first();
    $chain = Lead::query()->where('external_record_id', 'node/99')->first();

    expect($cafe)->not->toBeNull()
        ->and($cafe->company_name)->toBe('Cafe North')
        ->and($cafe->country)->toBe('PK')
        ->and($cafe->metadata['city'])->toBe('Lahore')
        ->and($cafe->metadata['state'])->toBe('Punjab')
        ->and($cafe->metadata['source_name'])->toBe('Google Maps')
        ->and($cafe->consent)->toBeFalse()
        ->and($cafe->source)->toBeInstanceOf(LeadSource::class)
        ->and($cafe->source->name)->toBe('Google Maps')
        ->and($chain->source->name)->toBe('Business Directory')
        ->and($chain->metadata['city'])->toBe('Lahore');

    $this->actingAs($this->user)->post('/discovery/import', [
        'search' => $query['search'],
        'ids' => ['ChIJ-cafe'],
    ]);

    expect(Lead::query()->where('external_record_id', 'ChIJ-cafe')->count())->toBe(1);
});

it('does not call Google Maps when the places key is missing', function (): void {
    config(['services.google.places_key' => null]);

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => '31.5', 'lon' => '74.3'],
        ]),
        'overpass-api.de/*' => Http::response(['elements' => []]),
    ]);
    Http::preventStrayRequests();

    $search = $this->actingAs($this->user)->post('/discovery/search', [
        'city' => 'Lahore',
        'country' => 'Pakistan',
        'category' => 'cafe',
        'source' => 'both',
    ])->assertRedirect();

    parse_str((string) parse_url($search->headers->get('Location'), PHP_URL_QUERY), $query);
    $cached = Cache::get('discovery.'.$this->tenant->id.'.'.$query['search']);

    expect($cached['notice'])->toContain('GOOGLE_PLACES_API_KEY')
        ->and($cached['businesses'])->toBe([]);

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'places.googleapis.com'));
});

it('refuses a business that was not in the search', function (): void {
    Http::fake([
        'places.googleapis.com/*' => Http::response(['places' => []]),
    ]);
    Http::preventStrayRequests();

    $search = $this->actingAs($this->user)->post('/discovery/search', [
        'city' => 'Lahore',
        'country' => 'Pakistan',
        'category' => 'cafe',
        'source' => 'google',
    ])->assertRedirect();

    parse_str((string) parse_url($search->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->actingAs($this->user)->post('/discovery/import', [
        'search' => $query['search'],
        'ids' => ['not-in-the-search'],
    ])->assertRedirect();

    expect(Lead::query()->count())->toBe(0);
});

it('requires a city and a known business type', function (): void {
    $this->actingAs($this->user)->post('/discovery/search', [
        'country' => 'Pakistan',
        'category' => 'not-a-type',
        'source' => 'directory',
    ])->assertSessionHasErrors(['city', 'category']);
});

it('forbids a user who cannot view leads', function (): void {
    $this->user->syncRoles([]);

    $this->actingAs($this->user)->get('/discovery')->assertForbidden();
});
