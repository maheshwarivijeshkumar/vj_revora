<?php

declare(strict_types=1);

namespace App\Domain\Leads\Enums;

/**
 * How a lead reached the workspace (§2).
 *
 * The UI must state this plainly on every lead and every source, because the
 * difference between an authorized API connection and a hand-uploaded file is
 * a compliance boundary, not a cosmetic label.
 */
enum LeadSourceType: string
{
    /** OAuth-connected provider, syncing through an authorized API. */
    case Connected = 'connected';
    /** A form we host, submitted with consent. */
    case Form = 'form';
    /** Customer-owned data, uploaded by the customer. */
    case Import = 'import';
    /** Pushed to us over the public API or an inbound webhook. */
    case Api = 'api';
    /** Entered by a person in the application. */
    case Manual = 'manual';
    /** Synced from the customer's own CRM. */
    case Crm = 'crm';

    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Connected account',
            self::Form => 'Website form',
            self::Import => 'Imported',
            self::Api => 'API',
            self::Manual => 'Entered manually',
            self::Crm => 'External CRM',
        };
    }

    /**
     * Whether the data came from a provider API we are authorized to read.
     *
     * Drives the provenance badge on the lead record: an imported list and a
     * verified Lead Ads submission must never look the same.
     */
    public function isAuthorizedApi(): bool
    {
        return in_array($this, [self::Connected, self::Crm], true);
    }
}
