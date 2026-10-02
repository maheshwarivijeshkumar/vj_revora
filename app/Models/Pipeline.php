<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Deals\Enums\PipelineEntity;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\PipelineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A board: an ordered set of stages something moves through (§22).
 *
 * @property string $name
 * @property string $key
 * @property PipelineEntity $entity_type
 * @property bool $is_default
 */
final class Pipeline extends Model
{
    /** @use HasFactory<PipelineFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $guarded = [];

    /** @return HasMany<PipelineStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(PipelineStage::class)->orderBy('sort_order');
    }

    /** @return HasMany<Deal, $this> */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /** The stage a new deal starts in. */
    public function firstStage(): ?PipelineStage
    {
        return $this->stages()->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_type' => PipelineEntity::class,
            'is_default' => 'boolean',
        ];
    }

    /**
     * The default deal pipeline a new workspace starts with (§7, §22).
     *
     * @return list<array{name: string, key: string, probability: int, is_won?: bool, is_lost?: bool, required_fields?: list<string>}>
     */
    public static function defaultStages(): array
    {
        return [
            ['name' => 'New', 'key' => 'new', 'probability' => 10],
            ['name' => 'Contacted', 'key' => 'contacted', 'probability' => 20],
            ['name' => 'Qualified', 'key' => 'qualified', 'probability' => 40],
            ['name' => 'Demo scheduled', 'key' => 'demo', 'probability' => 55],
            // A proposal without a value is not a proposal, so the stage
            // refuses to accept one (§22).
            ['name' => 'Proposal sent', 'key' => 'proposal', 'probability' => 70, 'required_fields' => ['value']],
            ['name' => 'Negotiation', 'key' => 'negotiation', 'probability' => 85],
            ['name' => 'Won', 'key' => 'won', 'probability' => 100, 'is_won' => true],
            ['name' => 'Lost', 'key' => 'lost', 'probability' => 0, 'is_lost' => true],
        ];
    }
}
