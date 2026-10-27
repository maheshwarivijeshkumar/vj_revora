<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A business the workspace sells as: an agency or a freelancer.
 *
 * Opportunities are scored against its services and skills, and drafted
 * proposals are written in its voice from its facts only.
 *
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $kind agency|freelancer
 * @property string|null $tagline
 * @property string|null $website
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $location
 * @property string|null $about
 * @property list<array{name: string, summary?: string, keywords?: list<string>}> $services
 * @property list<string>|null $skills
 * @property list<array{name: string, summary?: string, url?: string|null}>|null $portfolio
 * @property list<string>|null $markets
 * @property string|null $pricing
 * @property string|null $signature
 * @property bool $is_default
 */
final class ServiceProfile extends Model
{
    use BelongsToTenant;

    public const KIND_AGENCY = 'agency';

    public const KIND_FREELANCER = 'freelancer';

    protected $guarded = [];

    protected static function booted(): void
    {
        self::creating(function (self $profile): void {
            $profile->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** The profile that signs follow-ups when nothing more specific applies. */
    public static function default(): ?self
    {
        return self::query()->orderByDesc('is_default')->orderBy('id')->first();
    }

    public function isAgency(): bool
    {
        return $this->kind === self::KIND_AGENCY;
    }

    /**
     * Every phrase that signals work this profile does, lower-cased.
     *
     * @return list<string>
     */
    public function keywords(): array
    {
        $keywords = [];

        foreach ($this->services as $service) {
            foreach ($service['keywords'] ?? [] as $keyword) {
                $keywords[] = mb_strtolower(trim((string) $keyword));
            }
        }

        foreach ($this->skills ?? [] as $skill) {
            $keywords[] = mb_strtolower(trim((string) $skill));
        }

        return array_values(array_unique(array_filter($keywords)));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'services' => 'array',
            'skills' => 'array',
            'portfolio' => 'array',
            'markets' => 'array',
            'is_default' => 'boolean',
        ];
    }
}
