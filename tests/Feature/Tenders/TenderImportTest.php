<?php

declare(strict_types=1);

use App\Domain\Tenders\Enums\TenderTopic;
use App\Models\Tender;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/*
|--------------------------------------------------------------------------
| Tender import (PPRA)
|--------------------------------------------------------------------------
|
| Rows below mirror the markup of epms.ppra.gov.pk/public/tenders/active-tenders.
|
*/

beforeEach(function (): void {
    Http::preventStrayRequests();
    Sleep::fake();
});

function ppraRow(string $reference, string $title, string $sector = 'Info and Comm Tech', string $closing = 'Nov 13, 2026', string $time = '11:00 AM'): string
{
    return <<<HTML
        <tr class="cb-rt-main">
            <td class="cb-rt-arrow-cell"><button type="button" class="cb-rt-expand" data-id="1"></button></td>
            <td data-label="Sr" class="cb-rt-sr text-center">1</td>
            <td class="cb-rt-name-cell" data-label="Tender No"><div class="cb-rt-user"><span>
                <strong class="cb-rt-name text-primary fs-13">{$reference}</strong>
                <span class="cb-rt-dv d-block"><span class="badge bg-primary">Procurement</span></span>
            </span></div></td>
            <td class="cb-rt-name-cell" data-label="Tender Details"><span>
                <strong>{$title}<br>National Grid Company</strong>
                <small class="text-muted d-block">{$title} (short)</small>
                <div class="d-flex gap-2 flex-wrap mt-2">
                    <small class="badge bg-light text-dark">{$sector}</small>
                    <small class="text-muted badge bg-light text-dark">REF-39-2026</small>
                </div>
            </span></td>
            <td data-label="Status" class="text-center"><span class="badge badge-success">Published</span></td>
            <td data-label="Advertised">Oct 26, 2026</td>
            <td data-label="Closing"><strong class="d-block">{$closing}</strong><small class="text-muted">{$time}</small></td>
            <td data-label="Actions"><a href="/public/tenders/tender-details/{$reference}" class="btn">view</a></td>
        </tr>
        <tr class="cb-rt-detail"><td colspan="8"><div class="cb-rt-detail-card"><div class="cb-rt-grid">
            <div><span class="cb-rt-dl">Organization</span><span class="cb-rt-dv">
                <strong><i></i> Ministry of Energy (Power Division)</strong><br>
                <span class="d-block text-dark mt-1"><i></i> National Grid Company of Pakistan (NGC)</span>
                <small class="text-muted d-block mt-1"><i></i> Lahore - Pakistan</small>
            </span></div>
            <div><span class="cb-rt-dl">Tender Type</span><span class="cb-rt-dv"><span>Tender Notice</span></span></div>
            <div><span class="cb-rt-dl">Procurement Category</span><span class="cb-rt-dv"><span>Consultancy Services</span></span></div>
            <div><span class="cb-rt-dl">Sector</span><span class="cb-rt-dv"><span>{$sector}</span></span></div>
            <div><span class="cb-rt-dl">Tender Nature</span><span class="cb-rt-dv">Local</span></div>
        </div></div></td></tr>
        HTML;
}

function ppraPage(string ...$rows): string
{
    return '<html><body><table><tbody>'.implode('', $rows).'</tbody></table></body></html>';
}

function fakePpra(array $pages): void
{
    $sequence = Http::sequence();

    foreach ($pages as $page) {
        $sequence->push($page);
    }

    $sequence->push(ppraPage());

    Http::fake(['epms.ppra.gov.pk/public/tenders/active-tenders*' => $sequence]);
}

it('stores every active tender with its issue date and submission deadline', function (): void {
    fakePpra([ppraPage(ppraRow('TS0000016812E', 'Development of web portal for complaints'))]);

    $this->artisan('tenders:fetch')->assertSuccessful();

    $tender = Tender::query()->sole();

    expect($tender->reference)->toBe('TS0000016812E')
        ->and($tender->title)->toBe('Development of web portal for complaints National Grid Company')
        ->and($tender->organization)->toBe('Ministry of Energy (Power Division)')
        ->and($tender->department)->toBe('National Grid Company of Pakistan (NGC)')
        ->and($tender->city)->toBe('Lahore')
        ->and($tender->procurement_category)->toBe('Consultancy Services')
        ->and($tender->buyer_reference)->toBe('REF-39-2026')
        ->and($tender->advertised_on->toDateString())->toBe('2026-10-26')
        // 11:00 AM in Karachi (UTC+5).
        ->and($tender->closing_at->utc()->toIso8601String())->toBe('2026-11-13T06:00:00+00:00')
        ->and($tender->url)->toBe('https://epms.ppra.gov.pk/public/tenders/tender-details/TS0000016812E');
});

it('tags web, software and IT tenders and keeps the rest as other', function (string $title, string $sector, TenderTopic $topic): void {
    fakePpra([ppraPage(ppraRow('TS1', $title, $sector))]);

    $this->artisan('tenders:fetch')->assertSuccessful();

    $tender = Tender::query()->sole();

    expect($tender->topic)->toBe($topic)
        ->and($tender->is_it_related)->toBe($topic !== TenderTopic::Other);
})->with([
    'website' => ['Design and development of official website', 'Services', TenderTopic::Web],
    'software' => ['Procurement and implementation of ERP', 'Services', TenderTopic::Software],
    'ICT sector' => ['Supply of network switches', 'Info and Comm Tech', TenderTopic::InformationTechnology],
    'IT in capitals' => ['Hiring of IT support staff', 'Services', TenderTopic::InformationTechnology],
    'lower-case "it" is not IT' => ['Supply of furniture and fixing it in offices', 'Furniture/Fixture', TenderTopic::Other],
]);

it('ignores boilerplate descriptions and office codes when tagging', function (): void {
    $row = str_replace(
        ['Tank dipping tape (short)', 'REF-39-2026'],
        ['Send your request through the SAP Ariba Portal', 'CLP/IT/1213'],
        ppraRow('TS1', 'Tank dipping tape', 'Equipments'),
    );
    fakePpra([ppraPage($row)]);

    $this->artisan('tenders:fetch')->assertSuccessful();

    expect(Tender::query()->sole()->topic)->toBe(TenderTopic::Other);
});

it('reads every page and stops at the first empty one', function (): void {
    fakePpra([
        ppraPage(ppraRow('TS1', 'Supply of laptops'), ppraRow('TS2', 'Supply of desks', 'Furniture/Fixture')),
        ppraPage(ppraRow('TS3', 'Mobile app development', 'Services')),
    ]);

    $this->artisan('tenders:fetch')->assertSuccessful();

    expect(Tender::query()->pluck('reference')->sort()->values()->all())->toBe(['TS1', 'TS2', 'TS3']);
    Http::assertSentCount(3);
    Sleep::assertSleptTimes(2);
});

it('updates a tender that is fetched again instead of duplicating it', function (): void {
    // Two runs: the second sees a corrigendum that moved the deadline.
    Http::fake(['epms.ppra.gov.pk/public/tenders/active-tenders*' => Http::sequence()
        ->push(ppraPage(ppraRow('TS1', 'Supply of laptops')))
        ->push(ppraPage())
        ->push(ppraPage(ppraRow('TS1', 'Supply of laptops', closing: 'Nov 20, 2026')))
        ->push(ppraPage()),
    ]);

    $this->artisan('tenders:fetch');
    $this->artisan('tenders:fetch');

    $tender = Tender::query()->sole();

    expect($tender->closing_at->setTimezone('Asia/Karachi')->toDateString())->toBe('2026-11-20');
});

it('fails loudly when PPRA cannot be reached', function (): void {
    Http::fake(['epms.ppra.gov.pk/*' => Http::response('Service unavailable', 503)]);

    $this->artisan('tenders:fetch')->assertFailed();

    expect(Tender::query()->count())->toBe(0);
});
