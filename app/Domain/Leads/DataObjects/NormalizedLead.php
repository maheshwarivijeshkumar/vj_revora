<?php

declare(strict_types=1);

namespace App\Domain\Leads\DataObjects;

use App\Models\Lead;

/**
 * A lead reduced to the common schema (§17).
 *
 * Every source — provider webhook, website form, CSV row, API call, manual
 * entry — becomes one of these before anything else touches it. Provider
 * fields that do not map are preserved in `metadata` rather than forced into
 * core columns, which §17 forbids.
 */
final readonly class NormalizedLead
{
    /**
     * @param  array<string, string>  $utm
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $fullName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $country = null,
        public ?string $companyName = null,
        public ?string $jobTitle = null,
        public ?string $website = null,
        public array $utm = [],
        public ?string $landingPage = null,
        public ?string $referrer = null,
        public ?string $externalSystem = null,
        public ?string $externalRecordType = null,
        public ?string $externalRecordId = null,
        public bool $consent = false,
        public ?string $consentSource = null,
        public array $metadata = [],
    ) {}

    /**
     * Columns to write on a Lead. Nulls are kept so a merge can distinguish
     * "not provided" from "provided as empty".
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->fullName,
            'email' => $this->email,
            'phone' => $this->phone,
            'country' => $this->country,
            'company_name' => $this->companyName,
            'job_title' => $this->jobTitle,
            'website' => $this->website,
            'utm' => $this->utm === [] ? null : $this->utm,
            'landing_page' => $this->landingPage,
            'referrer' => $this->referrer,
            'external_system' => $this->externalSystem,
            'external_record_type' => $this->externalRecordType,
            'external_record_id' => $this->externalRecordId,
            'consent' => $this->consent,
            'consent_source' => $this->consentSource,
            'metadata' => $this->metadata === [] ? null : $this->metadata,
        ];
    }

    /** Matchable email, for deduplication (§18). */
    public function normalizedEmail(): ?string
    {
        $email = $this->email === null ? null : mb_strtolower(trim($this->email));

        return $email === '' ? null : $email;
    }

    /** Matchable phone, for deduplication (§18). */
    public function normalizedPhone(): ?string
    {
        return Lead::normalisePhone($this->phone);
    }

    /** The domain part of a work email, used as a weak company signal. */
    public function emailDomain(): ?string
    {
        $email = $this->normalizedEmail();

        if ($email === null || ! str_contains($email, '@')) {
            return null;
        }

        return substr($email, strrpos($email, '@') + 1) ?: null;
    }

    /**
     * Whether there is enough here to be worth storing.
     *
     * A lead with no way to reach the person and no name is not a lead.
     */
    public function isUsable(): bool
    {
        return $this->normalizedEmail() !== null
            || $this->normalizedPhone() !== null
            || $this->externalRecordId !== null
            || ($this->fullName !== null && trim($this->fullName) !== '');
    }
}
