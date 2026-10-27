<?php

declare(strict_types=1);

namespace App\Domain\Ai;

use RuntimeException;

/**
 * Writes text with a language model: proposals, pitches, letters.
 */
interface TextGenerator
{
    /**
     * @throws RuntimeException when the provider could not write it.
     */
    public function generate(string $instructions, string $prompt): string;

    /** Which provider wrote it, recorded next to the draft. */
    public function name(): string;
}
