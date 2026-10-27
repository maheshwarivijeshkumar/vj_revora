<?php

declare(strict_types=1);

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\DataObjects\JobPostData;
use App\Models\JobPost;
use Illuminate\Support\Carbon;

/**
 * Stores job posts from a source, without duplicates.
 *
 * The same post seen again from the same source is refreshed; the same post
 * seen on a second board (same title and company) is skipped, so one job is
 * one opportunity however many boards carry it.
 */
final class ImportJobPosts
{
    /** How long a post on one board blocks its copy on another. */
    private const DUPLICATE_WINDOW_DAYS = 30;

    /**
     * @param  iterable<JobPostData>  $posts
     * @return array{fetched: int, created: int, updated: int, duplicates: int}
     */
    public function handle(iterable $posts): array
    {
        $counts = ['fetched' => 0, 'created' => 0, 'updated' => 0, 'duplicates' => 0];
        $now = Carbon::now();

        foreach ($posts as $post) {
            $counts['fetched']++;
            $fingerprint = JobPost::fingerprintFor($post->title, $post->company);

            $existing = JobPost::query()
                ->where('source', $post->source)
                ->where('external_id', $post->externalId)
                ->first();

            if ($existing === null) {
                $elsewhere = JobPost::query()
                    ->where('fingerprint', $fingerprint)
                    ->where('source', '!=', $post->source)
                    ->where('last_seen_at', '>=', $now->copy()->subDays(self::DUPLICATE_WINDOW_DAYS))
                    ->exists();

                if ($elsewhere) {
                    $counts['duplicates']++;

                    continue;
                }
            }

            $job = $existing ?? new JobPost(['source' => $post->source, 'external_id' => $post->externalId]);

            $job->fill([
                'title' => mb_substr($post->title, 0, 500),
                'company' => $post->company === null ? null : mb_substr($post->company, 0, 255),
                'description' => $post->description,
                'url' => $post->url,
                'location' => $post->location === null ? null : mb_substr($post->location, 0, 255),
                'is_remote' => $post->isRemote,
                'job_type' => $post->jobType === null ? null : mb_substr($post->jobType, 0, 255),
                'tags' => array_slice($post->tags, 0, 30),
                'budget_type' => $post->budgetType,
                'budget_min' => $post->budgetMin,
                'budget_max' => $post->budgetMax,
                'currency' => $post->currency === null ? null : mb_substr($post->currency, 0, 3),
                'signals' => $post->signals === [] ? null : $post->signals,
                'fingerprint' => $fingerprint,
                'posted_at' => $post->postedAt,
                'last_seen_at' => $now,
            ]);

            $job->first_seen_at ??= $now;
            $created = ! $job->exists;
            $job->save();

            $created ? $counts['created']++ : $counts['updated']++;
        }

        return $counts;
    }
}
