<?php

declare(strict_types=1);

use App\Models\Tender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/*
|--------------------------------------------------------------------------
| UK government tender feeds
|--------------------------------------------------------------------------
|
| Find a Tender and Contracts Finder speak OCDS. Public Contracts Scotland
| returns its published contract-notice form. PPRA is covered separately.
|
*/

beforeEach(function (): void {
    Http::preventStrayRequests();
    Sleep::fake();
});

function quietOtherPortals(array $overrides): void
{
    Http::fake([
        'epms.ppra.gov.pk/*' => Http::response('<html></html>'),
        'www.find-tender.service.gov.uk/*' => Http::response(['releases' => []]),
        'www.contractsfinder.service.gov.uk/*' => Http::response(['releases' => []]),
        'api.publiccontractsscotland.gov.uk/*' => Http::response(['notices' => []]),
        ...$overrides,
    ]);
}

it('stores an open Find a Tender notice against the UK', function (): void {
    quietOtherPortals([
        'www.find-tender.service.gov.uk/*' => Http::response([
            'releases' => [[
                'ocid' => 'ocds-h6vhtk-079230',
                'id' => '101965-2026',
                'date' => '2026-10-28T11:19:55Z',
                'tag' => ['tender'],
                'tender' => [
                    'title' => 'Development of a web portal for passenger information',
                    'status' => 'active',
                    'description' => 'A public website and content management system.',
                    'mainProcurementCategory' => 'services',
                    'procurementMethodDetails' => 'Open procedure',
                    'classification' => ['description' => 'Software package and information systems'],
                    'tenderPeriod' => ['endDate' => '2026-12-04T12:00:00Z'],
                ],
                'buyer' => ['id' => 'GB-FTS-1', 'name' => 'South Yorkshire Mayoral Combined Authority'],
                'parties' => [[
                    'id' => 'GB-FTS-1',
                    'name' => 'South Yorkshire Mayoral Combined Authority',
                    'roles' => ['buyer'],
                    'address' => ['locality' => 'Sheffield', 'countryName' => 'United Kingdom'],
                ]],
            ]],
        ]),
    ]);

    $this->artisan('tenders:fetch')->assertSuccessful();

    $tender = Tender::query()->where('source', 'find-a-tender')->sole();

    expect($tender->country)->toBe('United Kingdom')
        ->and($tender->reference)->toBe('ocds-h6vhtk-079230')
        ->and($tender->organization)->toBe('South Yorkshire Mayoral Combined Authority')
        ->and($tender->city)->toBe('Sheffield')
        ->and($tender->procurement_category)->toBe('services')
        ->and($tender->closing_at->utc()->toIso8601String())->toBe('2026-12-04T12:00:00+00:00')
        ->and($tender->url)->toBe('https://www.find-tender.service.gov.uk/Notice/101965-2026')
        ->and($tender->topic->value)->toBe('web');
});

it('stores a Contracts Finder notice using the official notice link', function (): void {
    quietOtherPortals([
        'www.contractsfinder.service.gov.uk/*' => Http::response([
            'releases' => [[
                'ocid' => 'ocds-b5fd17-297251fc',
                'id' => 'feb3f774-76a0-4b8e-ac15-6c91276cfd3e-919450',
                'date' => '2026-10-28T11:10:03Z',
                'tender' => [
                    'title' => 'Software support for a rail systems team',
                    'status' => 'active',
                    'mainProcurementCategory' => 'services',
                    'tenderPeriod' => ['endDate' => '2026-11-11T17:06:00Z'],
                    'documents' => [[
                        'url' => 'https://www.contractsfinder.service.gov.uk/Notice/feb3f774-76a0-4b8e-ac15-6c91276cfd3e',
                    ]],
                ],
                'buyer' => ['id' => 'GB-CFS-1', 'name' => 'Ferrovial BAM Rail Systems'],
                'parties' => [[
                    'id' => 'GB-CFS-1',
                    'name' => 'Ferrovial BAM Rail Systems',
                    'roles' => ['buyer'],
                    'address' => ['locality' => 'London'],
                ]],
            ]],
        ]),
    ]);

    $this->artisan('tenders:fetch')->assertSuccessful();

    $tender = Tender::query()->where('source', 'contracts-finder')->sole();

    expect($tender->country)->toBe('United Kingdom')
        ->and($tender->city)->toBe('London')
        ->and($tender->url)->toBe('https://www.contractsfinder.service.gov.uk/Notice/feb3f774-76a0-4b8e-ac15-6c91276cfd3e')
        ->and($tender->topic->value)->toBe('software');
});

it('stores a Public Contracts Scotland contract notice', function (): void {
    quietOtherPortals([
        'api.publiccontractsscotland.gov.uk/*' => Http::response([
            'notices' => [[
                'Form_Section' => [
                    'Notice_UUID' => 'f79f810d-3f72-4379-b911-9dcda68a58af',
                    'F02_2014' => [
                        'Contracting_Body' => [
                            'Address_Contracting_Body' => [
                                'OfficialName' => 'North Ayrshire Council',
                                'Town' => 'Irvine',
                            ],
                            'URL_Participation' => 'https://www.publictendersscotland.publiccontractsscotland.gov.uk',
                        ],
                        'Object_Contract' => [[
                            'Title' => 'Design and development of a council website',
                            'Reference_Number' => 'NAC/5243',
                            'Short_Descr' => 'A new public website.',
                            'Type_Contract' => ['CType' => 'SERVICES'],
                            'CPV_Main' => ['CPV_Code' => ['Code' => '72000000']],
                        ]],
                        'Procedure' => [
                            'Date_Receipt_Tenders' => '2026-11-27',
                            'Time_Receipt_Tenders' => '12:00',
                        ],
                        'Complementary_Info' => ['Date_Dispatch_Notice' => '2026-10-28'],
                    ],
                ],
            ]],
        ]),
    ]);

    $this->artisan('tenders:fetch')->assertSuccessful();

    $tender = Tender::query()->where('source', 'public-contracts-scotland')->sole();

    expect($tender->country)->toBe('United Kingdom')
        ->and($tender->reference)->toBe('f79f810d-3f72-4379-b911-9dcda68a58af')
        ->and($tender->buyer_reference)->toBe('NAC/5243')
        ->and($tender->organization)->toBe('North Ayrshire Council')
        ->and($tender->city)->toBe('Irvine')
        ->and($tender->procurement_category)->toBe('Services')
        ->and($tender->closing_at->utc()->toIso8601String())->toBe('2026-11-27T12:00:00+00:00')
        ->and($tender->topic->value)->toBe('web');
});
