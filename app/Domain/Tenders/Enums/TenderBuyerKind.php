<?php

declare(strict_types=1);

namespace App\Domain\Tenders\Enums;

/**
 * Who is buying: a ministry, an autonomous body, a private firm, or a private industrial buyer.
 */
enum TenderBuyerKind: string
{
    case Public = 'public';
    case SemiGovernment = 'semi_government';
    case Private = 'private';
    case PrivateIndustry = 'private_industry';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::SemiGovernment => 'Semi-government',
            self::Private => 'Private',
            self::PrivateIndustry => 'Private industry',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Public => 'info',
            self::SemiGovernment => 'warning',
            self::Private => 'qualified',
            self::PrivateIndustry => 'contacted',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $kind): array => ['value' => $kind->value, 'label' => $kind->label()],
            self::cases(),
        );
    }
}
