<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Tenders\Actions\ImportTenders;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Pulls Government of Pakistan tenders from PPRA (scheduled twice a day).
 */
final class FetchTenders extends Command
{
    protected $signature = 'tenders:fetch';

    protected $description = 'Fetch active Government of Pakistan tenders from PPRA and tag the web, software and IT ones';

    public function handle(ImportTenders $import): int
    {
        try {
            $counts = $import->handle();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Fetched %d tenders: %d new, %d updated, %d web/software/IT.',
            $counts['fetched'],
            $counts['created'],
            $counts['updated'],
            $counts['it_related'],
        ));

        return self::SUCCESS;
    }
}
