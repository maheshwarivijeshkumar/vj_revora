<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Opportunities\Actions\MatchAllWorkspaces;
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

    public function handle(ImportTenders $import, MatchAllWorkspaces $match): int
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

        // Scored against each workspace's services, so a tender for SEO or
        // branding reaches the agency even when PPRA files it under "Other".
        $this->info('New tender matches: '.$match->handle());

        return self::SUCCESS;
    }
}
