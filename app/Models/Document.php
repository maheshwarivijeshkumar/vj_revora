<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A file attached to a CRM record (§21).
 *
 * @property int $id
 * @property string $uuid
 * @property int $tenant_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $extension
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $checksum
 * @property string|null $title
 * @property string|null $description
 * @property int|null $uploaded_by
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
final class Document extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    /**
     * Routed by uuid, so a download URL does not expose how many files a
     * workspace holds or let one be guessed by counting up.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Newest first: the file somebody just attached is the one being discussed.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDisplayOrder(Builder $query): void
    {
        $query->orderByDesc('id');
    }

    /**
     * Whether a browser may render this in place rather than download it.
     *
     * Driven by a short allowlist, not by "is it an image": an uploaded SVG or
     * HTML file rendered on this origin runs its own script against the
     * session that opened it.
     */
    public function isInlineSafe(): bool
    {
        /** @var list<string> $allowed */
        $allowed = config('documents.inline_types', []);

        return in_array($this->mime_type, $allowed, true);
    }

    /**
     * The name a person sees, which is not necessarily the stored filename.
     */
    public function displayName(): string
    {
        return $this->title ?? $this->original_name;
    }

    /**
     * A size a person can read, rounded to one decimal from 1 KB up.
     */
    public function sizeForHumans(): string
    {
        $bytes = $this->size_bytes;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        foreach (['KB', 'MB', 'GB'] as $unit) {
            $bytes /= 1024;

            if ($bytes < 1024 || $unit === 'GB') {
                return Str::of((string) round($bytes, 1))
                    ->rtrim('0')
                    ->rtrim('.')
                    ->append(' ', $unit)
                    ->toString();
            }
        }

        return $bytes.' B';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }
}
