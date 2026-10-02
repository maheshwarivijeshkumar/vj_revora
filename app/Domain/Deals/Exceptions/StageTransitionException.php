<?php

declare(strict_types=1);

namespace App\Domain\Deals\Exceptions;

use RuntimeException;

/**
 * A stage move that the pipeline's own rules reject.
 *
 * Carries the offending fields so the UI can point at them rather than
 * showing a generic failure (§59).
 */
final class StageTransitionException extends RuntimeException
{
    /**
     * @param  list<string>  $missingFields
     */
    public function __construct(string $message, public readonly array $missingFields = [])
    {
        parent::__construct($message);
    }

    /**
     * @param  list<string>  $fields
     */
    public static function missingRequiredFields(string $stage, array $fields): self
    {
        return new self(
            sprintf(
                'This deal cannot move to %s until %s %s filled in.',
                $stage,
                implode(', ', $fields),
                count($fields) === 1 ? 'is' : 'are',
            ),
            $fields,
        );
    }

    public static function wrongPipeline(): self
    {
        return new self('That stage belongs to a different pipeline.');
    }
}
