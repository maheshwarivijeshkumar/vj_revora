<?php

declare(strict_types=1);

namespace App\Domain\Opportunities\Sources;

use App\Domain\Opportunities\Contracts\JobSource;
use App\Domain\Opportunities\DataObjects\JobPostData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Arbeitnow's free job board API. Mostly on-site European roles, so only
 * the remote ones are kept, and only the first pages (newest first).
 *
 * @see https://www.arbeitnow.com/blog/job-board-api
 */
final class ArbeitnowSource implements JobSource
{
    private const PAGES = 3;

    public function key(): string
    {
        return 'arbeitnow';
    }

    public function fetch(): iterable
    {
        for ($page = 1; $page <= self::PAGES; $page++) {
            try {
                $response = Http::withUserAgent(SourceAgent::value())
                    ->timeout(60)
                    ->get('https://www.arbeitnow.com/api/job-board-api', ['page' => $page]);
            } catch (ConnectionException $e) {
                throw new RuntimeException('Could not reach Arbeitnow: '.$e->getMessage(), previous: $e);
            }

            if (! $response->successful()) {
                throw new RuntimeException('Arbeitnow returned HTTP '.$response->status());
            }

            foreach ((array) $response->json('data', []) as $job) {
                if (($job['remote'] ?? false) !== true || empty($job['slug']) || empty($job['title'])) {
                    continue;
                }

                yield new JobPostData(
                    source: $this->key(),
                    externalId: (string) $job['slug'],
                    title: (string) $job['title'],
                    url: (string) $job['url'],
                    company: $job['company_name'] ?? null,
                    description: $job['description'] ?? null,
                    location: $job['location'] ?? 'Remote',
                    jobType: implode(', ', array_map('strval', (array) ($job['job_types'] ?? []))) ?: null,
                    tags: array_values(array_map('strval', (array) ($job['tags'] ?? []))),
                    postedAt: isset($job['created_at']) ? Carbon::createFromTimestamp((int) $job['created_at']) : null,
                );
            }

            if ($response->json('links.next') === null) {
                return;
            }
        }
    }
}
