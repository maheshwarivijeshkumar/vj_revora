<?php

declare(strict_types=1);

namespace App\Domain\Opportunities\Sources;

/**
 * The User-Agent every source sees, so each board can tell who is calling
 * and how to reach us.
 */
final class SourceAgent
{
    public static function value(): string
    {
        return 'RevoraCRM opportunity feed (+'.config('app.url').')';
    }
}
