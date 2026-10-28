<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A notice for one person.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $title
 * @property string $body
 * @property string|null $href
 * @property Carbon|null $read_at
 */
final class UserNotification extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
