<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A company as the public API presents it (§47).
 *
 * @mixin Company
 */
final class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            // The normalised host, which is what matching is done on. A caller
            // syncing from elsewhere should compare against this, not website.
            'domain' => $this->domain,
            'website' => $this->website,
            'industry' => $this->industry,
            'size' => $this->size,
            'country' => $this->country,
            'phone' => $this->phone,
            'owner' => $this->whenLoaded('owner', fn (): ?array => $this->owner === null ? null : [
                'id' => $this->owner->uuid,
                'name' => $this->owner->name,
            ]),
            'contacts_count' => $this->whenCounted('contacts'),
            'deals_count' => $this->whenCounted('deals'),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
