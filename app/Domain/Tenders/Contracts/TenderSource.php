<?php

declare(strict_types=1);

namespace App\Domain\Tenders\Contracts;

use App\Domain\Tenders\DataObjects\TenderNotice;
use RuntimeException;

/**
 * A public procurement portal read through its own published feed.
 *
 * Every source here is an official government notice list. Nothing here
 * scrapes a private profile or a login-walled portal.
 */
interface TenderSource
{
    public function key(): string;

    /**
     * @return iterable<TenderNotice>
     *
     * @throws RuntimeException when the portal cannot be read.
     */
    public function fetchAll(): iterable;
}
