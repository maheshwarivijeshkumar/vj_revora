<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Scoring;

/**
 * How interested a lead sounded, out of 10, and why in a few words (§19).
 *
 * 0 is reserved for "never replied"; any reply scores at least 1, because
 * even "not interested" is an answer a person took the time to send.
 */
final readonly class ReplyScore
{
    public function __construct(
        public int $score,
        public string $reason,
        public string $scoredBy,
    ) {}

    public static function make(mixed $score, mixed $reason, string $scoredBy): self
    {
        return new self(
            score: max(1, min(10, (int) $score)),
            reason: mb_substr(trim((string) $reason), 0, 300) ?: 'Replied',
            scoredBy: $scoredBy,
        );
    }
}
