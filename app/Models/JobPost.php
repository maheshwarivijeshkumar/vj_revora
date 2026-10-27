<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A public job post from Upwork or a remote job board.
 *
 * Not tenant-owned: the same post is the same for every workspace. Whether
 * it fits, and what was done with it, lives in OpportunityMatch.
 *
 * @property int $id
 * @property string $source
 * @property string $external_id
 * @property string $title
 * @property string|null $company
 * @property string|null $description
 * @property string $url
 * @property string|null $location
 * @property bool $is_remote
 * @property string|null $job_type
 * @property list<string>|null $tags
 * @property string|null $budget_type
 * @property string|null $budget_min
 * @property string|null $budget_max
 * @property string|null $currency
 * @property array<string, mixed>|null $signals
 * @property string $fingerprint
 * @property Carbon|null $posted_at
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 */
final class JobPost extends Model
{
    protected $guarded = [];

    /** Display names of the sources, which their terms ask us to credit. */
    public const SOURCES = [
        'upwork' => 'Upwork',
        'remotive' => 'Remotive',
        'remoteok' => 'Remote OK',
        'arbeitnow' => 'Arbeitnow',
    ];

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? ucfirst($this->source);
    }

    /** "$500 fixed", "$25–45/hr", or null when the post gave no figure. */
    public function budgetLabel(): ?string
    {
        if ($this->budget_min === null && $this->budget_max === null) {
            return null;
        }

        $symbol = match ($this->currency) {
            'USD', null => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => $this->currency.' ',
        };

        $format = fn (?string $v): string => $v === null ? '' : $symbol.number_format((float) $v);
        $range = $this->budget_min !== null && $this->budget_max !== null && $this->budget_min !== $this->budget_max
            ? $format($this->budget_min).'–'.$format($this->budget_max)
            : $format($this->budget_max ?? $this->budget_min);

        return match ($this->budget_type) {
            'hourly' => $range.'/hr',
            'salary' => $range.'/yr',
            default => $range.' fixed',
        };
    }

    /**
     * Same post, different board: lower-cased title and company with the
     * punctuation and filler removed.
     */
    public static function fingerprintFor(string $title, ?string $company): string
    {
        $normalise = fn (string $text): string => trim((string) preg_replace(
            '/\s+/',
            ' ',
            (string) preg_replace('/[^a-z0-9 ]/', ' ', mb_strtolower($text)),
        ));

        return hash('sha256', $normalise($title).'|'.$normalise((string) $company));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_remote' => 'boolean',
            'tags' => 'array',
            'signals' => 'array',
            'posted_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
