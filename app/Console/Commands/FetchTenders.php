<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Opportunities\Actions\MatchAllWorkspaces;
use App\Domain\Tenders\Actions\ImportTenders;
use App\Domain\Tenders\Contracts\TenderSource;
use App\Domain\Tenders\Services\ContractsFinderSource;
use App\Domain\Tenders\Services\FindATenderSource;
use App\Domain\Tenders\Services\PpraTenderSource;
use App\Domain\Tenders\Services\PublicContractsScotlandSource;
use App\Domain\Tenders\TenderPortal;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Pulls government tender notices and scores them for every workspace.
 *
 * One portal being down does not stop the others. The command fails only
 * when none of them can be read.
 */
final class FetchTenders extends Command
{
    /** @var list<class-string<TenderSource>> */
    private const SOURCES = [
        PpraTenderSource::class,
        FindATenderSource::class,
        ContractsFinderSource::class,
        PublicContractsScotlandSource::class,
    ];

    protected $signature = 'tenders:fetch {--source=* : Portal to read. Omit to read every portal.}';

    protected $description = 'Fetch government tenders and tag the web, software and IT ones';

    public function handle(ImportTenders $import, MatchAllWorkspaces $match): int
    {
        $classes = $this->sources();

        if ($classes === []) {
            $this->error('None of those portals are known. Use ppra, find-a-tender, contracts-finder or public-contracts-scotland.');

            return self::FAILURE;
        }

        $read = 0;

        foreach ($classes as $class) {
            /** @var TenderSource $source */
            $source = app($class);

            try {
                $counts = $import->handle($source->fetchAll());
                $read++;
                $this->line(sprintf(
                    '%s: %d fetched, %d new, %d updated, %d web/software/IT.',
                    TenderPortal::label($source->key()),
                    $counts['fetched'],
                    $counts['created'],
                    $counts['updated'],
                    $counts['it_related'],
                ));
            } catch (RuntimeException $e) {
                $this->warn(TenderPortal::label($source->key()).': '.$e->getMessage());
            }
        }

        if ($read === 0) {
            $this->error('No government tender portal could be read.');

            return self::FAILURE;
        }

        // Scored against each workspace's services, so a tender for SEO or
        // branding still reaches the agency when the portal files it as other.
        $this->info('New tender matches: '.$match->handle());

        return self::SUCCESS;
    }

    /**
     * @return list<class-string<TenderSource>>
     */
    private function sources(): array
    {
        $wanted = array_values(array_filter(
            (array) $this->option('source'),
            fn (mixed $source): bool => is_string($source) && $source !== '',
        ));

        if ($wanted === []) {
            return self::SOURCES;
        }

        return array_values(array_filter(
            self::SOURCES,
            fn (string $class): bool => in_array(app($class)->key(), $wanted, true),
        ));
    }
}
