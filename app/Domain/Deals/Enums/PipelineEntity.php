<?php

declare(strict_types=1);

namespace App\Domain\Deals\Enums;

/**
 * What a pipeline moves (§22).
 *
 * A workspace may run a deal board and a lead board side by side, with
 * different stages, which is why the pipeline declares its subject rather
 * than the application assuming deals.
 */
enum PipelineEntity: string
{
    case Deal = 'deal';
    case Lead = 'lead';

    public function label(): string
    {
        return match ($this) {
            self::Deal => 'Deals',
            self::Lead => 'Leads',
        };
    }
}
