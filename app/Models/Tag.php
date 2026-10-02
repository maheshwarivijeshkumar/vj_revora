<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

/** A label applied across entities (§21). */
final class Tag extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected static function booted(): void
    {
        self::saving(function (self $tag): void {
            $tag->slug = Str::slug($tag->name);
        });
    }

    /** @return MorphToMany<Lead, $this> */
    public function leads(): MorphToMany
    {
        return $this->morphedByMany(Lead::class, 'taggable');
    }
}
