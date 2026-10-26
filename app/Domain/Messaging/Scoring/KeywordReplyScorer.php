<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Scoring;

/**
 * Scores a reply by the words in it. Used when no AI provider is configured,
 * and as the fallback when one fails.
 */
final class KeywordReplyScorer implements ReplyScorer
{
    /**
     * Checked in order; the first match wins, so refusals are tested before
     * the interest words they often contain ("not interested in a demo").
     *
     * @var list<array{0: int, 1: string, 2: list<string>}>
     */
    private const RULES = [
        [1, 'Asked to stop or not interested', [
            'unsubscribe', 'remove me', 'stop emailing', 'stop messaging', 'not interested',
            'no thanks', 'no thank you', 'wrong person', 'do not contact', "don't contact",
        ]],
        [3, 'Not now, maybe later', ['not now', 'maybe later', 'next year', 'next quarter', 'busy right now', 'reach out later']],
        [9, 'Asked for a call, demo or pricing', [
            'call me', 'schedule a call', 'book a call', 'meeting', 'demo', 'quote', 'quotation',
            'price', 'pricing', 'cost', 'proposal', 'whatsapp me',
        ]],
        [7, 'Interested and asking questions', ['interested', 'tell me more', 'more information', 'more details', 'how does', 'can you', 'send me']],
    ];

    public function score(string $reply, ?string $subject = null): ReplyScore
    {
        $text = mb_strtolower($reply);

        foreach (self::RULES as [$score, $reason, $phrases]) {
            foreach ($phrases as $phrase) {
                if (str_contains($text, $phrase)) {
                    return ReplyScore::make($score, $reason, 'keywords');
                }
            }
        }

        return ReplyScore::make(5, 'Replied; intent unclear', 'keywords');
    }
}
