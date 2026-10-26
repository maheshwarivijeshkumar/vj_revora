<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenders\Enums\TenderTopic;
use Database\Factories\TenderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A public procurement notice (PPRA EPMS).
 *
 * Deliberately not tenant-owned: every workspace sees the same public notices,
 * so there is nothing to scope.
 *
 * @property int $id
 * @property string $source
 * @property string $reference
 * @property string $title
 * @property string|null $description
 * @property string|null $buyer_reference
 * @property string|null $organization
 * @property string|null $department
 * @property string|null $city
 * @property string|null $sector
 * @property string|null $procurement_category
 * @property string|null $tender_type
 * @property string|null $method
 * @property string|null $nature
 * @property string|null $status
 * @property Carbon|null $advertised_on
 * @property Carbon|null $closing_at
 * @property TenderTopic $topic
 * @property bool $is_it_related
 * @property string $url
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 */
final class Tender extends Model
{
    /** @use HasFactory<TenderFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * Still open for bids.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->where('closing_at', '>', now());
    }

    public function isOpen(): bool
    {
        return $this->closing_at !== null && $this->closing_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'advertised_on' => 'date',
            'closing_at' => 'datetime',
            'topic' => TenderTopic::class,
            'is_it_related' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
