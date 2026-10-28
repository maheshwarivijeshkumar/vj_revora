<?php

declare(strict_types=1);

namespace App\Domain\Discovery\Actions;

use App\Domain\Discovery\DataObjects\DiscoveredBusiness;
use App\Domain\Discovery\DataObjects\DiscoveryQuery;
use App\Domain\Discovery\Sources\BusinessDirectorySource;
use App\Domain\Discovery\Sources\GooglePlacesSource;
use App\Models\Lead;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Asks the selected sources for businesses in one city and remembers the
 * result long enough for the user to choose which ones become leads.
 */
final readonly class SearchBusinesses
{
    public function __construct(
        private GooglePlacesSource $google,
        private BusinessDirectorySource $directory,
    ) {}

    /**
     * @return array{id: string, businesses: list<array<string, mixed>>, notice: string|null}
     */
    public function handle(DiscoveryQuery $query, int $tenantId): array
    {
        $found = [];
        $notices = [];

        if ($query->source === 'google' || $query->source === 'both') {
            $batch = $this->google->search($query);
            $found = [...$found, ...$batch['businesses']];

            if ($batch['notice'] !== null) {
                $notices[] = $batch['notice'];
            }
        }

        if ($query->source === 'directory' || $query->source === 'both') {
            $batch = $this->directory->search($query);
            $found = [...$found, ...$batch['businesses']];

            if ($batch['notice'] !== null) {
                $notices[] = $batch['notice'];
            }
        }

        usort($found, function (DiscoveredBusiness $left, DiscoveredBusiness $right): int {
            return [$left->likelyChain, $left->name] <=> [$right->likelyChain, $right->name];
        });

        $rows = array_map(fn (DiscoveredBusiness $business): array => $business->toArray(), $found);
        $id = (string) Str::uuid();
        $notice = $notices === [] ? null : implode(' ', array_unique($notices));

        Cache::put($this->cacheKey($tenantId, $id), [
            'businesses' => $rows,
            'notice' => $notice,
        ], now()->addMinutes(30));

        return [
            'id' => $id,
            'businesses' => $this->withCrmStatus($rows),
            'notice' => $notice,
        ];
    }

    /**
     * @return array{businesses: list<array<string, mixed>>, notice: string|null}|null
     */
    public function recall(int $tenantId, string $id): ?array
    {
        if (preg_match('/^[0-9a-f-]{36}$/i', $id) !== 1) {
            return null;
        }

        $cached = Cache::get($this->cacheKey($tenantId, $id));

        if (! is_array($cached) || ! is_array($cached['businesses'] ?? null)) {
            return null;
        }

        return [
            'businesses' => $this->withCrmStatus($cached['businesses']),
            'notice' => is_string($cached['notice'] ?? null) ? $cached['notice'] : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withCrmStatus(array $rows): array
    {
        $ids = array_values(array_filter(array_map(
            fn (array $row): string => (string) ($row['external_id'] ?? ''),
            $rows,
        )));

        $existing = $ids === []
            ? collect()
            : Lead::query()
                ->master()
                ->whereIn('external_system', ['google_places', 'openstreetmap'])
                ->whereIn('external_record_id', $ids)
                ->get(['external_system', 'external_record_id']);

        $known = $existing
            ->map(fn (Lead $lead): string => $lead->external_system.'|'.$lead->external_record_id)
            ->all();

        return array_map(function (array $row) use ($known): array {
            $row['in_crm'] = in_array(($row['provider'] ?? '').'|'.($row['external_id'] ?? ''), $known, true);

            return $row;
        }, $rows);
    }

    private function cacheKey(int $tenantId, string $id): string
    {
        return 'discovery.'.$tenantId.'.'.$id;
    }
}
