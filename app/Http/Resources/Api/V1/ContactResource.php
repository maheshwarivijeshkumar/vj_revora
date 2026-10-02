<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Company;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A contact as the public API presents it (§47).
 *
 * @mixin Contact
 */
final class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // The uuid is the public identifier, so an internal schema change
            // cannot break an integration.
            'id' => $this->uuid,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'job_title' => $this->job_title,
            'country' => $this->country,
            'owner' => $this->whenLoaded('owner', fn (): ?array => $this->owner === null ? null : [
                'id' => $this->owner->uuid,
                'name' => $this->owner->name,
            ]),
            'companies' => $this->whenLoaded(
                'companies',
                fn (): array => $this->companies
                    ->map(fn (Company $company): array => [
                        'id' => $company->uuid,
                        'name' => $company->name,
                        'domain' => $company->domain,
                        'role' => $company->pivot?->role,
                        'is_primary' => (bool) $company->pivot?->is_primary,
                    ])
                    ->all(),
            ),
            // Present when this person came in as a lead, so the acquisition
            // story survives conversion (§85).
            'lead_id' => $this->whenLoaded('lead', fn (): ?string => $this->lead?->uuid),
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
