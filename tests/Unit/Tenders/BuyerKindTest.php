<?php

declare(strict_types=1);

use App\Domain\Tenders\Enums\TenderBuyerKind;
use App\Domain\Tenders\Services\BuyerKindClassifier;

it('classifies the buyer from the organization name', function (string $organization, TenderBuyerKind $kind): void {
    expect((new BuyerKindClassifier)->classify($organization))->toBe($kind);
})->with([
    'ministry' => ['Ministry of Finance', TenderBuyerKind::Public],
    'authority' => ['Capital Development Authority', TenderBuyerKind::SemiGovernment],
    'power company' => ['Faisalabad Electric Supply Company (FESCO)', TenderBuyerKind::SemiGovernment],
    'public university' => ['COMSATS University Islamabad', TenderBuyerKind::SemiGovernment],
    'private university' => ['Lahore University of Management Sciences (LUMS)', TenderBuyerKind::Private],
    'private club' => ['Islamabad Club', TenderBuyerKind::Private],
    'private industry' => ['Acme Industries (Pvt) Ltd', TenderBuyerKind::PrivateIndustry],
    'private firm' => ['Northwind Software (Pvt) Ltd', TenderBuyerKind::Private],
    'state complex' => ['Heavy Mechanical Complex', TenderBuyerKind::SemiGovernment],
    'state shipyard' => ['Karachi Shipyard and Engineering Works Ltd', TenderBuyerKind::SemiGovernment],
    'industries ministry' => ['Ministry of Industries & Production (MoIP)', TenderBuyerKind::Public],
    'ordnance factories' => ['Pakistan Ordnance Factories (POF)', TenderBuyerKind::SemiGovernment],
    'steel mills' => ['Pakistan Steel Mills', TenderBuyerKind::SemiGovernment],
]);

it('uses the department when the organization is missing', function (): void {
    expect((new BuyerKindClassifier)->classify(null, 'Capital Development Authority'))
        ->toBe(TenderBuyerKind::SemiGovernment);
});

it('keeps a semi-government buyer filed under a ministry', function (): void {
    expect((new BuyerKindClassifier)->classify(
        'Ministry of Energy (Power Division)',
        'Material Management Directorate, Lahore Electric Supply Company Ltd (LESCO)',
    ))->toBe(TenderBuyerKind::SemiGovernment);
});
