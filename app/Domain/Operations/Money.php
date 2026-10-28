<?php

declare(strict_types=1);

namespace App\Domain\Operations;

/**
 * Two-decimal money stored as a string so a float never becomes the record.
 */
final class Money
{
    public static function of(float|int|string $amount): string
    {
        return number_format(round((float) $amount, 2), 2, '.', '');
    }

    public static function display(float|int|string $amount): string
    {
        return number_format((float) $amount, 2, '.', ',');
    }
}
