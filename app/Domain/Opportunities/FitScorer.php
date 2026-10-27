<?php

declare(strict_types=1);

namespace App\Domain\Opportunities;

use App\Models\JobPost;
use App\Models\ServiceProfile;
use App\Models\Tender;

/**
 * Scores a tender or job post 0–100 against what a profile sells.
 *
 * Deliberately rules, not a model: every point has a reason a person can
 * read and disagree with, and the fix for a bad score is a keyword on the
 * Services page rather than a retrain (§19).
 *
 *   service keywords in the title     up to 60
 *   service keywords in the body/tags up to 20
 *   the profile's own tech skills     up to 15
 *   quality signals                   ±
 */
final class FitScorer
{
    /** Words that mean the post is not paid work. */
    private const RED_FLAGS = ['unpaid', 'equity only', 'internship', 'volunteer', 'commission only', 'free trial task'];

    private const CASE_SENSITIVE = ['ai', 'pos', 'mis'];

    /**
     * Roles a services business cannot deliver as a contractor: the company
     * wants a salesperson or a support agent, not software.
     */
    private const NOT_SERVICE_WORK = [
        'sales', 'account executive', 'account manager', 'business developer', 'business development',
        'recruiter', 'talent acquisition', 'customer success', 'customer support', 'support agent',
        'ai trainer', 'annotation', 'data labeling', 'data labelling', 'tutor', 'teacher',
    ];

    /** Postings that need fluent German, which neither profile offers. */
    private const GERMAN_ONLY = ['(m/w/d)', '(w/m/d)', '(m/f/d)', 'deutschkenntnisse', 'fließend deutsch', 'fluent german', 'german (c1)', 'german c1', 'werkstudent'];

    public function score(ServiceProfile $profile, Tender|JobPost $opportunity): FitScore
    {
        [$title, $body, $tags] = $this->text($opportunity);

        $score = 0;
        $services = [];
        $keywords = [];
        $reasons = [];
        $titleHits = 0;
        $bodyHits = 0;

        foreach ($profile->services as $service) {
            $matched = false;

            foreach ($service['keywords'] ?? [] as $keyword) {
                if ($this->mentions($title, (string) $keyword)) {
                    $titleHits++;
                    $matched = true;
                    $keywords[] = (string) $keyword;
                } elseif ($this->mentions($body.' '.$tags, (string) $keyword)) {
                    $bodyHits++;
                    $matched = true;
                    $keywords[] = (string) $keyword;
                }
            }

            if ($matched) {
                $services[] = (string) $service['name'];
            }
        }

        // A government tender's title is its whole scope, so a match there
        // carries more than one keyword in a job post's long title.
        $score += min(60, $titleHits * ($opportunity instanceof Tender ? 40 : 30));
        $score += min(20, $bodyHits * 5);

        $skillHits = 0;

        foreach ($profile->skills ?? [] as $skill) {
            if ($this->mentions($title.' '.$tags.' '.$body, (string) $skill)) {
                $skillHits++;
                $keywords[] = (string) $skill;
            }
        }

        $score += min(15, $skillHits * 5);

        if ($services !== []) {
            $reasons[] = 'Asks for: '.implode(', ', array_slice($services, 0, 3));
        }

        if ($skillHits > 0) {
            $reasons[] = "Uses {$skillHits} of your technologies";
        }

        [$adjust, $more] = $opportunity instanceof Tender
            ? $this->tenderSignals($opportunity)
            : $this->jobSignals($opportunity);

        $score += $adjust;
        $reasons = [...$reasons, ...$more];

        foreach (self::NOT_SERVICE_WORK as $role) {
            if ($this->mentions($title, $role)) {
                $score -= 40;
                $reasons[] = 'A '.$role.' hire, not project work';

                break;
            }
        }

        if ($this->isGerman($title, $body)) {
            $score -= 20;
            $reasons[] = 'German-language role';
        }

        foreach (self::RED_FLAGS as $flag) {
            if ($this->mentions($title.' '.$body, $flag)) {
                $score -= 40;
                $reasons[] = "Says “{$flag}”";

                break;
            }
        }

        // Nothing it asks for is something this profile sells.
        if ($services === []) {
            $score = min($score, 20);
        }

        return new FitScore(
            score: max(0, min(100, $score)),
            services: $services,
            keywords: array_values(array_unique($keywords)),
            reasons: $reasons,
            skillHits: $skillHits,
        );
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function text(Tender|JobPost $opportunity): array
    {
        if ($opportunity instanceof Tender) {
            return [
                $opportunity->title,
                (string) $opportunity->description,
                implode(' ', array_filter([$opportunity->sector, $opportunity->procurement_category])),
            ];
        }

        $body = trim(strip_tags(html_entity_decode((string) $opportunity->description)));

        return [$opportunity->title, mb_substr($body, 0, 6000), implode(' ', $opportunity->tags ?? [])];
    }

    /**
     * @return array{0: int, 1: list<string>}
     */
    private function tenderSignals(Tender $tender): array
    {
        $adjust = 0;
        $reasons = [];

        if (! $tender->isOpen()) {
            return [-100, ['Closed']];
        }

        $category = mb_strtolower((string) $tender->procurement_category);

        // Buying laptops is not hiring a services company.
        if ($category === 'goods') {
            $adjust -= 25;
            $reasons[] = 'Goods purchase, not services';
        } elseif (str_contains($category, 'services')) {
            $adjust += 20;
            $reasons[] = $tender->procurement_category.' tender';
        }

        if ($tender->closing_at !== null && $tender->closing_at->diffInDays(now(), absolute: true) < 3) {
            $adjust -= 10;
            $reasons[] = 'Closes in under 3 days';
        }

        return [$adjust, $reasons];
    }

    /**
     * @return array{0: int, 1: list<string>}
     */
    private function jobSignals(JobPost $post): array
    {
        $adjust = 0;
        $reasons = [];
        $signals = $post->signals ?? [];

        if ($post->posted_at !== null && $post->posted_at->greaterThan(now()->subDays(3))) {
            $adjust += 5;
            $reasons[] = 'Posted in the last 3 days';
        } elseif ($post->posted_at !== null && $post->posted_at->lessThan(now()->subDays(30))) {
            $adjust -= 15;
            $reasons[] = 'Over a month old';
        }

        if ($post->budget_max !== null || $post->budget_min !== null) {
            $adjust += 3;
        }

        if (($signals['payment_verified'] ?? false) === true) {
            $adjust += 5;
            $reasons[] = 'Payment verified';
        }

        if ((float) ($signals['client_spent'] ?? 0) >= 1000) {
            $adjust += 5;
            $reasons[] = 'Client has spent $'.number_format((float) $signals['client_spent']);
        }

        if ((int) ($signals['applicants'] ?? 0) >= 50) {
            $adjust -= 10;
            $reasons[] = $signals['applicants'].'+ applicants already';
        }

        return [$adjust, $reasons];
    }

    /** Marked as German, or written in it. */
    private function isGerman(string $title, string $body): bool
    {
        $text = mb_strtolower($title.' '.$body);

        foreach (self::GERMAN_ONLY as $marker) {
            if (str_contains($text, $marker)) {
                return true;
            }
        }

        $hits = 0;

        foreach ([' und ', ' der ', ' die ', ' wir ', ' für ', ' mit ', ' sie ', ' bei '] as $word) {
            $hits += substr_count($text, $word);
        }

        return $hits >= 8;
    }

    private function mentions(string $text, string $keyword): bool
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return false;
        }

        // "AI", "POS" and "MIS" are also ordinary letters ("Mis-", "pos."), so
        // they only count in capitals. Everything else is whole-word, any case.
        $acronym = in_array(mb_strtolower($keyword), self::CASE_SENSITIVE, true);
        $needle = $acronym ? mb_strtoupper($keyword) : $keyword;

        // Plurals count: "web applications" is asking for a web application.
        $plural = preg_match('/[a-z]$/i', $keyword) === 1 ? '(?:e?s)?' : '';

        return preg_match('/(?<![\w.])'.preg_quote($needle, '/').$plural.'(?![\w])/'.($acronym ? 'u' : 'iu'), $text) === 1;
    }
}
