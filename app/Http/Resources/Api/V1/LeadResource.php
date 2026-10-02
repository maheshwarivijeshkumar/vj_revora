<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lead as the public API presents it (§47).
 *
 * The public shape is deliberately not the database shape: internal ids stay
 * internal, and the uuid is the identifier callers hold. Renaming a column
 * later must not break somebody's integration.
 *
 * @mixin Lead
 */
final class LeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'company_name' => $this->company_name,
            'job_title' => $this->job_title,
            'website' => $this->website,
            'status' => $this->status->value,
            'score' => $this->score,
            'score_band' => $this->score_band->value,
            'owner' => $this->whenLoaded('owner', fn (): ?array => $this->owner === null ? null : [
                'id' => $this->owner->uuid,
                'name' => $this->owner->name,
            ]),
            'source' => $this->whenLoaded('source', fn (): ?array => $this->source === null ? null : [
                'key' => $this->source->key,
                'name' => $this->source->name,
                'type' => $this->source->type->value,
                // §2: a caller must be able to tell an authorized provider
                // feed from an uploaded list.
                'authorized_api' => $this->source->isAuthorizedApi(),
            ]),
            'utm' => $this->utm,
            'consent' => $this->consent,
            'consent_at' => $this->consent_at?->toIso8601String(),
            'external' => [
                'system' => $this->external_system,
                'record_type' => $this->external_record_type,
                'record_id' => $this->external_record_id,
            ],
            'metadata' => $this->metadata,
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'next_follow_up_at' => $this->next_follow_up_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
