<?php

declare(strict_types=1);

namespace App\Domain\Leads\Services;

use App\Domain\Leads\DataObjects\ScoreResult;
use App\Domain\Leads\Enums\ScoreBand;
use App\Domain\Leads\Enums\ScoreMethod;
use App\Models\Lead;
use App\Models\LeadScore;
use App\Models\LeadScoreRule;
use Illuminate\Support\Collection;

/**
 * Rule-based lead scoring (§19).
 *
 * Every score carries the reasons that produced it. That is the whole design
 * constraint: a rep who cannot see why a lead scored 82 has no way to trust it
 * or to correct it, and will end up ignoring the number entirely.
 *
 * AI scoring lands in Phase 4 and will contribute reasons to the same list
 * rather than replacing it (§20).
 */
final class LeadScorer
{
    /** Scores are clamped to this range, whatever the rules add up to. */
    private const MIN = 0;

    private const MAX = 100;

    public function score(Lead $lead, ScoreMethod $method = ScoreMethod::Rules): ScoreResult
    {
        $rules = LeadScoreRule::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $total = 0;
        $reasons = [];

        foreach ($rules as $rule) {
            if (! $this->matches($lead, $rule)) {
                continue;
            }

            $total += $rule->points;
            $reasons[] = ['label' => $rule->name, 'points' => $rule->points];
        }

        $clamped = max(self::MIN, min(self::MAX, $total));

        return new ScoreResult(
            score: $clamped,
            band: ScoreBand::forScore($clamped, $this->thresholds()),
            method: $method,
            reasons: $reasons,
        );
    }

    /** Scores a lead, persists the result and records the run. */
    public function apply(Lead $lead, ScoreMethod $method = ScoreMethod::Rules): ScoreResult
    {
        $result = $this->score($lead, $method);

        $lead->score = $result->score;
        $lead->score_band = $result->band;
        $lead->save();

        LeadScore::create([
            'lead_id' => $lead->id,
            'score' => $result->score,
            'band' => $result->band->value,
            'method' => $result->method->value,
            'reasons' => $result->reasons,
            'computed_at' => now(),
        ]);

        return $result;
    }

    /**
     * Evaluates one rule against a lead.
     *
     * Conditions are stored as `{ field, operator, value }`. Unknown fields
     * and operators return false rather than throwing: a misconfigured rule
     * should quietly not fire, not break ingestion for every lead behind it.
     */
    private function matches(Lead $lead, LeadScoreRule $rule): bool
    {
        $condition = $rule->condition;
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? 'is_present';

        if (! is_string($field)) {
            return false;
        }

        $actual = $this->resolveField($lead, $field);
        $expected = $condition['value'] ?? null;

        return match ($operator) {
            'is_present' => $this->present($actual),
            'is_absent' => ! $this->present($actual),
            'equals' => $this->present($actual)
                && mb_strtolower((string) $actual) === mb_strtolower((string) $expected),
            'not_equals' => ! $this->present($actual)
                || mb_strtolower((string) $actual) !== mb_strtolower((string) $expected),
            'contains' => $this->present($actual)
                && is_string($expected)
                && str_contains(mb_strtolower((string) $actual), mb_strtolower($expected)),
            'in' => $this->present($actual)
                && is_array($expected)
                && in_array(mb_strtolower((string) $actual), array_map('mb_strtolower', $expected), true),
            'greater_than' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'less_than' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            default => false,
        };
    }

    /**
     * Reads a field from the lead, its UTM values or its metadata.
     *
     * `metadata.budget` and `utm.utm_source` are addressable, so rules can
     * target provider-specific answers without those needing core columns.
     */
    private function resolveField(Lead $lead, string $field): mixed
    {
        if (str_starts_with($field, 'metadata.')) {
            return data_get($lead->metadata, substr($field, 9));
        }

        if (str_starts_with($field, 'utm.')) {
            return data_get($lead->utm, substr($field, 4));
        }

        // Relations are not reachable here on purpose: a scoring rule that
        // triggers a query per lead per rule would make ingestion quadratic.
        return $lead->getAttribute($field);
    }

    private function present(mixed $value): bool
    {
        if ($value === null || $value === false) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return true;
    }

    /**
     * Workspace band thresholds, falling back to the defaults.
     *
     * @return array<string, int>
     */
    private function thresholds(): array
    {
        // Per-workspace overrides arrive with tenant settings in Phase 1.13;
        // until then every workspace uses the documented defaults (§119).
        return ScoreBand::DEFAULT_THRESHOLDS;
    }

    /**
     * The default rule set created for a new workspace (§7, §19).
     *
     * Mirrors the worked example in the specification, so a workspace scores
     * something sensible before anyone configures anything.
     *
     * @return list<array{name: string, condition: array<string, mixed>, points: int}>
     */
    public static function defaultRules(): array
    {
        return [
            ['name' => 'Email provided', 'condition' => ['field' => 'email', 'operator' => 'is_present'], 'points' => 10],
            ['name' => 'Phone provided', 'condition' => ['field' => 'phone', 'operator' => 'is_present'], 'points' => 10],
            ['name' => 'Company provided', 'condition' => ['field' => 'company_name', 'operator' => 'is_present'], 'points' => 10],
            ['name' => 'Job title provided', 'condition' => ['field' => 'job_title', 'operator' => 'is_present'], 'points' => 5],
            ['name' => 'Requested a demo', 'condition' => ['field' => 'metadata.interest', 'operator' => 'contains', 'value' => 'demo'], 'points' => 25],
            ['name' => 'Budget provided', 'condition' => ['field' => 'metadata.budget', 'operator' => 'is_present'], 'points' => 15],
            ['name' => 'Consent given', 'condition' => ['field' => 'consent', 'operator' => 'is_present'], 'points' => 5],
        ];
    }

    /**
     * @return Collection<int, LeadScoreRule>
     */
    public function rules(): Collection
    {
        return LeadScoreRule::query()->orderBy('sort_order')->get();
    }
}
