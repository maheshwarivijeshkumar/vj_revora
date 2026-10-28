<?php

declare(strict_types=1);

namespace App\Domain\Operations;

use Illuminate\Database\Eloquent\Model;

/**
 * The next EST-, INV-, or CO- number for this workspace.
 */
final class DocumentNumber
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function next(string $prefix, string $modelClass): string
    {
        $latest = $modelClass::query()->orderByDesc('id')->value('number');
        $current = 1000;

        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches) === 1) {
            $current = (int) $matches[1];
        }

        return sprintf('%s-%04d', $prefix, $current + 1);
    }
}
