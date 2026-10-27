<?php

declare(strict_types=1);

namespace App\Domain\Opportunities\Sources;

use App\Domain\Opportunities\Contracts\JobSource;
use App\Domain\Opportunities\DataObjects\JobPostData;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Remotive's public remote-jobs API.
 *
 * Its terms: link back to the Remotive URL and credit Remotive, and call at
 * most a few times a day. One call per run, twice a day, for every category.
 *
 * @see https://remotive.com/api-documentation
 */
final class RemotiveSource implements JobSource
{
    public function key(): string
    {
        return 'remotive';
    }

    public function fetch(): iterable
    {
        try {
            $response = Http::withUserAgent(SourceAgent::value())->timeout(60)->get('https://remotive.com/api/remote-jobs');
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach Remotive: '.$e->getMessage(), previous: $e);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Remotive returned HTTP '.$response->status());
        }

        foreach ((array) $response->json('jobs', []) as $job) {
            if (empty($job['id']) || empty($job['title']) || empty($job['url'])) {
                continue;
            }

            yield new JobPostData(
                source: $this->key(),
                externalId: (string) $job['id'],
                title: (string) $job['title'],
                url: (string) $job['url'],
                company: $job['company_name'] ?? null,
                description: $job['description'] ?? null,
                location: $job['candidate_required_location'] ?? null,
                jobType: $job['job_type'] ?? null,
                tags: array_values(array_map('strval', [...(array) ($job['tags'] ?? []), (string) ($job['category'] ?? '')])),
                budgetType: filled($job['salary'] ?? null) ? 'salary' : null,
                signals: array_filter(['salary_text' => $job['salary'] ?? null]),
                postedAt: $this->date($job['publication_date'] ?? null),
            );
        }
    }

    private function date(mixed $value): ?Carbon
    {
        try {
            return is_string($value) ? Carbon::parse($value, 'UTC') : null;
        } catch (Throwable) {
            return null;
        }
    }
}
