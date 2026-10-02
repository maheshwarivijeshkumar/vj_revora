<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A workspace-configured scoring rule (§19).
 *
 * `condition` is arbitrary JSON authored by the workspace, so it is typed
 * loosely on purpose: LeadScorer validates its shape at evaluation time and a
 * malformed rule must not fire rather than throw.
 *
 * @property array<string, mixed> $condition
 * @property int $points
 * @property string $name
 * @property bool $is_active
 */
final class LeadScoreRule extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condition' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
