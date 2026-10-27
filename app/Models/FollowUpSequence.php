<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An automatic run of follow-up emails for new leads.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property int $no_reply_after_minutes
 */
final class FollowUpSequence extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /** @return HasMany<FollowUpStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(FollowUpStep::class)->orderBy('position');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
