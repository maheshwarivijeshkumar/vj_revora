<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Scoring;

use RuntimeException;

/**
 * Judges a lead's reply to a follow-up.
 */
interface ReplyScorer
{
    /** The rubric every AI provider is given, so they score alike. */
    public const INSTRUCTIONS = <<<'TEXT'
        You score how interested a sales lead is, based on their reply to a follow-up email or WhatsApp message.
        Return a score from 1 to 10 and a reason of at most 15 words.
        10: ready to buy or book now. 8-9: asks for a call, demo, quote or pricing. 6-7: interested, asks questions.
        4-5: neutral or unclear. 2-3: not now, maybe later. 1: not interested, wrong person, or asks to stop or unsubscribe.
        Judge only the lead's own words. Ignore signatures and quoted earlier messages.
        TEXT;

    /**
     * @throws RuntimeException when the provider could not score it.
     */
    public function score(string $reply, ?string $subject = null): ReplyScore;
}
