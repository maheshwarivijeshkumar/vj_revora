<?php

declare(strict_types=1);

namespace App\Domain\Opportunities\Contracts;

use App\Domain\Opportunities\DataObjects\JobPostData;
use RuntimeException;

/**
 * A public job board read through its own API.
 *
 * Every source here publishes an API for exactly this use and asks only that
 * posts link back to it and credit it, which the Opportunities page does.
 * No source is scraped.
 */
interface JobSource
{
    public function key(): string;

    /**
     * @return iterable<JobPostData>
     *
     * @throws RuntimeException when the board cannot be read.
     */
    public function fetch(): iterable;
}
