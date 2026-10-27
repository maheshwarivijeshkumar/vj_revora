<?php

declare(strict_types=1);

namespace App\Domain\Integrations\DataObjects;

use Carbon\CarbonInterface;

/**
 * One lead-form submission as a provider returned it, flattened to answers.
 *
 * `fields` holds the form answers keyed by the provider's field name, which
 * LeadNormalizer resolves through its aliases; `attribution` becomes UTM
 * values, and `metadata` is kept on the lead verbatim (§17, §34).
 */
final readonly class ProviderLead
{
    /**
     * @param  array<string, string>  $fields
     * @param  array<string, string>  $attribution  utm_source, utm_medium, utm_campaign …
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $id,
        public CarbonInterface $submittedAt,
        public array $fields,
        public array $attribution = [],
        public array $metadata = [],
    ) {}

    /**
     * The payload handed to LeadNormalizer.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [...$this->fields, ...$this->attribution, ...$this->metadata];
    }
}
