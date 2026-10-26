<?php

declare(strict_types=1);

namespace App\Domain\Tenders\Enums;

/**
 * What a tender is for, as far as an IT business is concerned.
 */
enum TenderTopic: string
{
    case Web = 'web';
    case Software = 'software';
    case InformationTechnology = 'it';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Software => 'Software',
            self::InformationTechnology => 'IT',
            self::Other => 'Other',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Web => 'info',
            self::Software => 'qualified',
            self::InformationTechnology => 'contacted',
            self::Other => 'neutral',
        };
    }

    public function isItRelated(): bool
    {
        return $this !== self::Other;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $topic): array => ['value' => $topic->value, 'label' => $topic->label()],
            self::cases(),
        );
    }
}
