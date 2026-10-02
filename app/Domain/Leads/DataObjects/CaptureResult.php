<?php

declare(strict_types=1);

namespace App\Domain\Leads\DataObjects;

use App\Models\Lead;

/**
 * What the capture pipeline did (§85).
 *
 * Callers need to distinguish "a new lead exists" from "an existing lead was
 * enriched": the API returns a different status for each, and usage metering
 * must only count the former against the plan's lead quota.
 */
final readonly class CaptureResult
{
    /**
     * @param  list<string>  $filled  the fields an enrichment actually wrote
     */
    public function __construct(
        public Lead $lead,
        public bool $isDuplicate,
        public ?string $matchedOn,
        public ScoreResult $score,
        public ?int $assignedTo,
        public array $filled = [],
    ) {}

    public function isNew(): bool
    {
        return ! $this->isDuplicate;
    }

    /**
     * Whether a repeat touch actually taught us anything.
     *
     * Distinct from isDuplicate: seeing the same person again with the same
     * details is not a change, and consumers that announce changes should stay
     * quiet for it.
     */
    public function wasEnriched(): bool
    {
        return $this->isDuplicate && $this->filled !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lead_id' => $this->lead->id,
            'uuid' => $this->lead->uuid,
            'is_duplicate' => $this->isDuplicate,
            'matched_on' => $this->matchedOn,
            'score' => $this->score->toArray(),
            'assigned_to' => $this->assignedTo,
            'filled' => $this->filled,
        ];
    }
}
