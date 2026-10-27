<?php

declare(strict_types=1);

namespace App\Domain\Opportunities\Actions;

use App\Domain\Opportunities\Enums\OpportunityStatus;
use App\Domain\Opportunities\FitScore;
use App\Domain\Opportunities\FitScorer;
use App\Models\JobPost;
use App\Models\OpportunityMatch;
use App\Models\ServiceProfile;
use App\Models\Tender;
use Illuminate\Database\Eloquent\Collection;

/**
 * Scores every live tender and job post against the workspace's profiles and
 * keeps the ones worth a look (§19).
 *
 * Runs in the workspace's context. Each opportunity is matched to whichever
 * profile fits it best. Untouched matches are re-scored as the profiles
 * change; ones a person has acted on keep the score they were acted on.
 */
final readonly class MatchOpportunities
{
    /** Job posts older than this are not worth a proposal. */
    private const JOB_MAX_AGE_DAYS = 30;

    public function __construct(
        private FitScorer $scorer,
    ) {}

    /**
     * @return int new matches
     */
    public function handle(): int
    {
        /** @var Collection<int, ServiceProfile> $profiles */
        $profiles = ServiceProfile::query()->orderByDesc('is_default')->orderBy('id')->get();

        if ($profiles->isEmpty()) {
            return 0;
        }

        $existing = OpportunityMatch::query()
            ->get(['id', 'opportunity_type', 'opportunity_id', 'status'])
            ->keyBy(fn (OpportunityMatch $m): string => $m->opportunity_type.':'.$m->opportunity_id);

        $created = 0;

        Tender::query()->open()->chunkById(500, function (Collection $tenders) use ($profiles, $existing, &$created): void {
            foreach ($tenders as $tender) {
                $created += $this->consider(OpportunityMatch::TYPE_TENDER, $tender, $profiles, $existing->get('tender:'.$tender->id));
            }
        });

        JobPost::query()
            ->where('last_seen_at', '>=', now()->subDays(self::JOB_MAX_AGE_DAYS))
            ->where(fn ($q) => $q->whereNull('posted_at')->orWhere('posted_at', '>=', now()->subDays(self::JOB_MAX_AGE_DAYS)))
            ->chunkById(500, function (Collection $posts) use ($profiles, $existing, &$created): void {
                foreach ($posts as $post) {
                    $created += $this->consider(OpportunityMatch::TYPE_JOB_POST, $post, $profiles, $existing->get('job_post:'.$post->id));
                }
            });

        return $created;
    }

    /**
     * @param  Collection<int, ServiceProfile>  $profiles
     */
    private function consider(string $type, Tender|JobPost $opportunity, Collection $profiles, ?OpportunityMatch $match): int
    {
        if ($match !== null && $match->status !== OpportunityStatus::New) {
            return 0;
        }

        [$profile, $fit] = $this->best($profiles, $opportunity);

        if ($match !== null) {
            OpportunityMatch::query()->whereKey($match->id)->update([
                'service_profile_id' => $profile->id,
                'fit_score' => $fit->score,
                'fit_details' => json_encode($fit->toArray()),
                'updated_at' => now(),
            ]);

            return 0;
        }

        if ($fit->score < OpportunityMatch::MIN_FIT) {
            return 0;
        }

        OpportunityMatch::create([
            'opportunity_type' => $type,
            'opportunity_id' => $opportunity->id,
            'service_profile_id' => $profile->id,
            'fit_score' => $fit->score,
            'fit_details' => $fit->toArray(),
            'status' => OpportunityStatus::New,
        ]);

        return 1;
    }

    /**
     * Upwork hires individuals, so a tie there goes to the freelancer;
     * elsewhere to whichever profile's technologies the post names.
     */
    private function preferOnTie(ServiceProfile $candidate, FitScore $fit, ServiceProfile $current, FitScore $currentFit, Tender|JobPost $opportunity): bool
    {
        if ($opportunity instanceof JobPost && $opportunity->source === 'upwork' && $candidate->kind !== $current->kind) {
            return ! $candidate->isAgency();
        }

        return $fit->skillHits > $currentFit->skillHits;
    }

    /**
     * @param  Collection<int, ServiceProfile>  $profiles
     * @return array{0: ServiceProfile, 1: FitScore}
     */
    private function best(Collection $profiles, Tender|JobPost $opportunity): array
    {
        $best = null;

        foreach ($profiles as $profile) {
            $fit = $this->scorer->score($profile, $opportunity);

            // On a tie, the profile whose technologies the post names wins:
            // a Laravel job goes to the Laravel engineer. Otherwise the
            // default profile, which is first in the list.
            if ($best === null
                || $fit->score > $best[1]->score
                || ($fit->score === $best[1]->score && $this->preferOnTie($profile, $fit, $best[0], $best[1], $opportunity))) {
                $best = [$profile, $fit];
            }
        }

        /** @var array{0: ServiceProfile, 1: FitScore} $best */
        return $best;
    }
}
