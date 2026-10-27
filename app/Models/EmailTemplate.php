<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A reusable email with {{placeholders}} (§26).
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $subject
 * @property string $body
 */
final class EmailTemplate extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $template): void {
            $template->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
