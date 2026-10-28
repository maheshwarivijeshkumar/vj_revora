<?php

declare(strict_types=1);

namespace App\Domain\Tenders\Services;

use App\Domain\Tenders\Enums\TenderBuyerKind;

/**
 * Reads the buyer name and decides public, semi-government, private, or private industry.
 *
 * Government portals also publish autonomous bodies and the occasional private
 * firm, so the portal alone is not the buyer type. A ministry stays public.
 * An authority, university, or state-owned company is semi-government. A
 * private limited company is private, and one that manufactures is private industry.
 */
final class BuyerKindClassifier
{
    public function classify(?string $organization, ?string $department = null): TenderBuyerKind
    {
        $fromOrganization = $this->classifyName($organization);
        $fromDepartment = $this->classifyName($department);

        // The parent is often a ministry while the department is the body
        // that is actually buying, such as an electric supply company.
        return $this->rank($fromDepartment) > $this->rank($fromOrganization)
            ? $fromDepartment
            : $fromOrganization;
    }

    private function classifyName(?string $name): TenderBuyerKind
    {
        $name = $this->plain($name);

        if ($name === '') {
            return TenderBuyerKind::Public;
        }

        if ($this->matches($name, '/\b(lums|lahore university of management sciences|islamabad club|aga khan)\b/i')) {
            return TenderBuyerKind::Private;
        }

        // A ministry stays public even when its name contains "Industries".
        // A directorate of an electric company still counts as that company.
        if ($this->isPublicBody($name) && ! $this->isPrivateCompany($name) && ! $this->isSemiGovernment($name)) {
            return TenderBuyerKind::Public;
        }

        if ($this->isPrivateCompany($name)) {
            return $this->isIndustrial($name)
                ? TenderBuyerKind::PrivateIndustry
                : TenderBuyerKind::Private;
        }

        if ($this->isSemiGovernment($name)) {
            return TenderBuyerKind::SemiGovernment;
        }

        return TenderBuyerKind::Public;
    }

    private function rank(TenderBuyerKind $kind): int
    {
        return match ($kind) {
            TenderBuyerKind::Public => 1,
            TenderBuyerKind::SemiGovernment => 2,
            TenderBuyerKind::Private => 3,
            TenderBuyerKind::PrivateIndustry => 4,
        };
    }

    private function isPublicBody(string $name): bool
    {
        return $this->matches($name, '/\b(ministry|government|department|directorate|division|secretariat|cabinet|court|constabulary)\b/i');
    }

    private function isPrivateCompany(string $name): bool
    {
        return $this->matches($name, '/\(\s*pvt\.?\s*\)/i')
            || $this->matches($name, '/\bpvt\.?\s*ltd\b/i')
            || $this->matches($name, '/\bprivate\s+(limited|ltd)\b/i');
    }

    private function isIndustrial(string $name): bool
    {
        return $this->matches($name, '/\b(industries|industrial|factory|factories|mills|manufacturing)\b/i');
    }

    private function isSemiGovernment(string $name): bool
    {
        return $this->matches($name, '/\b(authority|university|institute|corporation|shipyard|wapda|complex|centre|center)\b/i')
            || $this->matches($name, '/\belectric\s+(supply|power)\b/i')
            || $this->matches($name, '/\b(limited|ltd)\b/i')
            || $this->matches($name, '/\b(development board|higher education commission)\b/i')
            || $this->matches($name, '/\b(ordnance factories|steel mills|machine tool factory)\b/i');
    }

    private function matches(string $name, string $pattern): bool
    {
        return preg_match($pattern, $name) === 1;
    }

    private function plain(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
