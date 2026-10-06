<?php

declare(strict_types=1);

namespace App\Domain\Leads\DataObjects;

use App\Domain\Leads\Enums\VerificationStatus;

/**
 * What verification found, and why (§19, §59).
 *
 * Carries its reasons for the same reason a score does: a rep told a lead is
 * "not usable" with no explanation cannot correct a typo, and will stop
 * trusting the flag.
 */
final readonly class VerificationResult
{
    /**
     * @param  list<array{check: string, verdict: string, detail: string}>  $findings
     */
    public function __construct(
        public VerificationStatus $status,
        public int $confidence,
        public array $findings = [],
    ) {}

    /**
     * The findings that argue against the lead, for showing first.
     *
     * @return list<array{check: string, verdict: string, detail: string}>
     */
    public function problems(): array
    {
        return array_values(array_filter(
            $this->findings,
            fn (array $finding): bool => $finding['verdict'] !== 'pass',
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'confidence' => $this->confidence,
            'findings' => $this->findings,
        ];
    }
}
