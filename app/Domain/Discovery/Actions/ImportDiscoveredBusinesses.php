<?php

declare(strict_types=1);

namespace App\Domain\Discovery\Actions;

use App\Domain\Discovery\DataObjects\DiscoveredBusiness;
use App\Domain\Leads\Actions\CaptureLead;
use App\Domain\Leads\DataObjects\NormalizedLead;
use App\Domain\Leads\Enums\LeadSourceType;
use App\Models\LeadSource;
use Illuminate\Support\Facades\Cache;

/**
 * Turns businesses the user picked from a search into ordinary CRM leads.
 *
 * Only rows from that search are accepted. The source is recorded as
 * Google Maps or Business Directory, and nothing is enrolled for automatic
 * outreach: these businesses did not ask to be contacted.
 */
final readonly class ImportDiscoveredBusinesses
{
    public function __construct(
        private CaptureLead $capture,
    ) {}

    /**
     * @param  list<string>  $externalIds
     * @return array{created: int, duplicates: int}
     */
    public function handle(int $tenantId, string $searchId, array $externalIds): array
    {
        $cached = Cache::get('discovery.'.$tenantId.'.'.$searchId);
        $rows = is_array($cached) ? ($cached['businesses'] ?? null) : null;

        if (! is_array($rows)) {
            return ['created' => 0, 'duplicates' => 0];
        }

        $wanted = array_fill_keys($externalIds, true);
        $created = 0;
        $duplicates = 0;

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($wanted[(string) ($row['external_id'] ?? '')])) {
                continue;
            }

            $business = DiscoveredBusiness::fromArray($row);
            $source = $this->source($business);
            $result = $this->capture->handle(
                new NormalizedLead(
                    fullName: $business->name,
                    phone: $business->phone,
                    country: $business->countryCode,
                    companyName: $business->name,
                    website: $business->website,
                    referrer: $business->sourceUrl,
                    externalSystem: $business->provider,
                    externalRecordType: 'business',
                    externalRecordId: $business->externalId,
                    metadata: array_filter([
                        'city' => $business->city,
                        'state' => $business->state,
                        'country_name' => $business->countryName,
                        'address' => $business->address,
                        'category' => $business->category,
                        'category_label' => $business->categoryLabel,
                        'source_name' => $business->sourceName,
                        'source_url' => $business->sourceUrl,
                        'likely_chain' => $business->likelyChain,
                        'fetched_at' => now()->toIso8601String(),
                    ], fn (mixed $value): bool => $value !== null && $value !== ''),
                ),
                $source,
                assign: true,
            );

            if ($result->isDuplicate) {
                $duplicates++;
            } else {
                $created++;
            }

            unset($wanted[$business->externalId]);
        }

        return ['created' => $created, 'duplicates' => $duplicates];
    }

    private function source(DiscoveredBusiness $business): LeadSource
    {
        $key = $business->provider === 'google_places' ? 'google_maps' : 'business_directory';

        return LeadSource::query()->firstOrCreate(
            ['key' => $key],
            [
                'name' => $business->sourceName,
                'type' => LeadSourceType::Import,
                'provider' => $business->provider,
            ],
        );
    }
}
