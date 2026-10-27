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
 * Remote OK's public API. The first element is its legal notice, not a job.
 *
 * Its terms: link back to the job on Remote OK and mention Remote OK as the
 * source.
 *
 * @see https://remoteok.com/api
 */
final class RemoteOkSource implements JobSource
{
    public function key(): string
    {
        return 'remoteok';
    }

    public function fetch(): iterable
    {
        try {
            $response = Http::withUserAgent(SourceAgent::value())->timeout(60)->get('https://remoteok.com/api');
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach Remote OK: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Remote OK returned HTTP '.$response->status());
        }

        foreach ((array) $response->json() as $job) {
            if (! is_array($job) || empty($job['id']) || empty($job['position']) || empty($job['url'])) {
                continue;
            }

            $min = (float) ($job['salary_min'] ?? 0);
            $max = (float) ($job['salary_max'] ?? 0);

            yield new JobPostData(
                source: $this->key(),
                externalId: (string) $job['id'],
                title: (string) $job['position'],
                url: (string) $job['url'],
                company: $job['company'] ?? null,
                description: $job['description'] ?? null,
                location: filled($job['location'] ?? null) ? (string) $job['location'] : 'Remote',
                tags: array_values(array_map('strval', (array) ($job['tags'] ?? []))),
                budgetType: $max > 0 ? 'salary' : null,
                budgetMin: $min > 0 ? $min : null,
                budgetMax: $max > 0 ? $max : null,
                currency: $max > 0 ? 'USD' : null,
                postedAt: isset($job['epoch']) ? Carbon::createFromTimestamp((int) $job['epoch']) : null,
            );
        }
    }
}
