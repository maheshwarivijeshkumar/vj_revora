<?php

declare(strict_types=1);

namespace App\Domain\Leads\Enums;

/**
 * Whether a lead's contact details look real (§18, §59).
 *
 * Four states rather than a boolean, because "we have not checked yet" and "we
 * checked and it is junk" are different facts, and a rep needs to know which.
 * Risky is the honest middle: a role address or a free mailbox is contactable
 * but tells you less than a named corporate one, and refusing it outright would
 * discard real buyers.
 */
enum VerificationStatus: string
{
    case Unverified = 'unverified';
    case Valid = 'valid';
    case Risky = 'risky';
    case Invalid = 'invalid';

    public function label(): string
    {
        return match ($this) {
            self::Unverified => 'Not checked',
            self::Valid => 'Verified',
            self::Risky => 'Needs a look',
            self::Invalid => 'Not usable',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Unverified => 'neutral',
            self::Valid => 'success',
            self::Risky => 'warning',
            self::Invalid => 'danger',
        };
    }

    /**
     * Whether it is reasonable to spend effort on this lead.
     *
     * Risky counts: the point of flagging it is to warn, not to bin it. Only an
     * address that cannot receive mail, or a number that cannot be dialled, is
     * excluded.
     */
    public function isWorkable(): bool
    {
        return $this !== self::Invalid;
    }

    /**
     * @return list<array{value: string, label: string, tone: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'tone' => $case->tone(),
            ],
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
