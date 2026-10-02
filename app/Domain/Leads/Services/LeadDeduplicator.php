<?php

declare(strict_types=1);

namespace App\Domain\Leads\Services;

use App\Domain\Leads\DataObjects\NormalizedLead;
use App\Models\Lead;
use App\Models\LeadMerge;
use Illuminate\Support\Facades\DB;

/**
 * Finds and folds together duplicate leads (§18).
 *
 * Matching is layered, most confident first. The first layer that hits wins,
 * so a provider's own record ID is never overruled by a shared phone number.
 *
 * Nothing is ever deleted. A duplicate becomes a tombstone pointing at the
 * master, so its history, source attribution and events all survive.
 */
final class LeadDeduplicator
{
    /** Match layers, in descending confidence (§18). */
    public const MATCH_EXTERNAL_ID = 'external_id';

    public const MATCH_EMAIL = 'email';

    public const MATCH_PHONE = 'phone';

    public const MATCH_NAME_AND_COMPANY = 'name_and_company';

    /**
     * The existing master this lead duplicates, if any.
     *
     * @return array{lead: Lead, matched_on: string}|null
     */
    public function findMaster(NormalizedLead $lead): ?array
    {
        foreach ($this->layers($lead) as $matchedOn => $resolve) {
            $existing = $resolve();

            if ($existing instanceof Lead) {
                return ['lead' => $existing, 'matched_on' => $matchedOn];
            }
        }

        return null;
    }

    /**
     * @return array<string, callable(): ?Lead>
     */
    private function layers(NormalizedLead $lead): array
    {
        $layers = [];

        // 1. The provider's own identifier. Unambiguous where present.
        if ($lead->externalSystem !== null && $lead->externalRecordId !== null) {
            $layers[self::MATCH_EXTERNAL_ID] = fn (): ?Lead => Lead::query()
                ->master()
                ->where('external_system', $lead->externalSystem)
                ->where('external_record_id', $lead->externalRecordId)
                ->first();
        }

        // 2. Normalised email. Strong for B2B, where addresses are personal.
        if (($email = $lead->normalizedEmail()) !== null) {
            $layers[self::MATCH_EMAIL] = fn (): ?Lead => Lead::query()
                ->master()
                ->where('email_normalized', $email)
                ->first();
        }

        // 3. Normalised phone.
        if (($phone = $lead->normalizedPhone()) !== null) {
            $layers[self::MATCH_PHONE] = fn (): ?Lead => Lead::query()
                ->master()
                ->where('phone_normalized', $phone)
                ->first();
        }

        // 4. Name plus company. Weakest layer, and deliberately the narrowest:
        //    it needs both parts, because name alone collides constantly and
        //    merging two different people is far worse than keeping two rows
        //    for one person.
        if ($lead->fullName !== null && $lead->companyName !== null) {
            $layers[self::MATCH_NAME_AND_COMPANY] = fn (): ?Lead => Lead::query()
                ->master()
                ->whereRaw('LOWER(full_name) = ?', [mb_strtolower(trim($lead->fullName))])
                ->whereRaw('LOWER(company_name) = ?', [mb_strtolower(trim($lead->companyName))])
                ->first();
        }

        return $layers;
    }

    /**
     * Folds a duplicate's information into the master.
     *
     * Only fills gaps: an existing value is never overwritten, because the
     * earlier record was verified by whatever process created it and a later
     * form fill is not automatically more correct. Conflicts are recorded in
     * the merge diff so a human can settle them.
     *
     * @return array<string, mixed> the fields that were filled
     */
    public function mergeInto(Lead $master, NormalizedLead $duplicate): array
    {
        $filled = [];

        foreach ($duplicate->toAttributes() as $field => $value) {
            if ($value === null || $value === [] || $value === false) {
                continue;
            }

            // metadata and utm merge rather than replace, so a second touch
            // adds its attribution instead of discarding the first.
            if (in_array($field, ['metadata', 'utm'], true)) {
                $existing = is_array($master->{$field}) ? $master->{$field} : [];
                $merged = [...$existing, ...(array) $value];

                if ($merged !== $existing) {
                    $master->{$field} = $merged;
                    $filled[$field] = $merged;
                }

                continue;
            }

            if ($master->{$field} === null || $master->{$field} === '') {
                $master->{$field} = $value;
                $filled[$field] = $value;
            }
        }

        // Last touch always advances: it is the whole point of the field.
        if ($duplicate->utm !== [] || $duplicate->landingPage !== null) {
            $master->last_touch = array_filter([
                'utm' => $duplicate->utm === [] ? null : $duplicate->utm,
                'landing_page' => $duplicate->landingPage,
                'referrer' => $duplicate->referrer,
                'at' => now()->toIso8601String(),
            ]);
            $filled['last_touch'] = $master->last_touch;
        }

        return $filled;
    }

    /**
     * Retires an existing lead into a master, keeping both rows.
     *
     * Used when two records that already exist turn out to be the same person,
     * rather than when an incoming payload matches one.
     */
    public function tombstone(Lead $master, Lead $duplicate, string $matchedOn, ?int $mergedBy = null): LeadMerge
    {
        return DB::transaction(function () use ($master, $duplicate, $matchedOn, $mergedBy): LeadMerge {
            $duplicate->merged_into_id = $master->id;
            $duplicate->save();

            // Events follow the person, so the master's timeline is complete.
            $duplicate->events()->update(['lead_id' => $master->id]);

            return LeadMerge::create([
                'master_lead_id' => $master->id,
                'merged_lead_id' => $duplicate->id,
                'matched_on' => $matchedOn,
                'diff' => ['retired' => $duplicate->only(['email', 'phone', 'full_name', 'company_name'])],
                'merged_by' => $mergedBy,
                'merged_at' => now(),
            ]);
        });
    }
}
