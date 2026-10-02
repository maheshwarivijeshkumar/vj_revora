<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Deal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A deal as the public API presents it (§47).
 *
 * @mixin Deal
 */
final class DealResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'value' => (float) $this->value,
            'currency' => $this->currency,
            'probability' => $this->probability,
            // Included because it is the number a forecast is built from, and
            // every caller would otherwise recompute it themselves.
            'weighted_value' => $this->weightedValue(),
            'status' => $this->status->value,
            'pipeline' => $this->whenLoaded('pipeline', fn (): array => [
                'id' => $this->pipeline->id,
                'name' => $this->pipeline->name,
            ]),
            'stage' => $this->whenLoaded('stage', fn (): array => [
                'id' => $this->stage->id,
                'key' => $this->stage->key,
                'name' => $this->stage->name,
                'probability' => $this->stage->probability,
            ]),
            'owner' => $this->whenLoaded('owner', fn (): ?array => $this->owner === null ? null : [
                'id' => $this->owner->uuid,
                'name' => $this->owner->name,
            ]),
            'company' => $this->whenLoaded('company', fn (): ?array => $this->company === null ? null : [
                'id' => $this->company->uuid,
                'name' => $this->company->name,
            ]),
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'lost_reason' => $this->lost_reason,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
