<?php

declare(strict_types=1);

namespace App\Domain\Messaging\DataObjects;

use Carbon\CarbonInterface;

/**
 * An email read out of a connected mailbox's inbox.
 */
final readonly class IncomingEmail
{
    public function __construct(
        public string $messageId,
        public string $fromAddress,
        public ?string $fromName,
        public ?string $subject,
        public string $textBody,
        public CarbonInterface $receivedAt,
        public ?string $inReplyTo = null,
    ) {}

    /**
     * The reply without the quoted thread below it, which is what a person
     * reads and what scoring should judge.
     */
    public function freshText(): string
    {
        $lines = [];

        foreach (preg_split('/\R/u', $this->textBody) ?: [] as $line) {
            // "On Mon, 27 Oct 2026, Sales wrote:" and Outlook's divider both
            // mark where the quoted history starts.
            if (preg_match('/^\s*(On .+wrote:|-{2,}\s*Original Message|From:\s.+@)/i', $line) === 1) {
                break;
            }

            if (! str_starts_with(ltrim($line), '>')) {
                $lines[] = $line;
            }
        }

        return trim(implode("\n", $lines));
    }
}
