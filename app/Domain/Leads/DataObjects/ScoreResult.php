<?php

declare(strict_types=1);

namespace App\Domain\Leads\DataObjects;

use App\Domain\Leads\Enums\ScoreBand;
use App\Domain\Leads\Enums\ScoreMethod;

/**
 * The outcome of one scoring run (§19).
 *
 * `reasons` is the user-facing explanation and is always populated, even when
 * the score is zero: "nothing matched" is itself useful feedback.
 */
final readonly class ScoreResult
{
    /**
     * @param  list<array{label: string, points: int}>  $reasons
     */
    public function __construct(
        public int $score,
        public ScoreBand $band,
        public ScoreMethod $method,
        public array $reasons,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'band' => $this->band->value,
            'band_label' => $this->band->label(),
            'method' => $this->method->value,
            'reasons' => $this->reasons,
        ];
    }
}
