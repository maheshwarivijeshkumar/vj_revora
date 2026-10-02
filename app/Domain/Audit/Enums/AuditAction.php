<?php

declare(strict_types=1);

namespace App\Domain\Audit\Enums;

/**
 * The actions worth a permanent record (§54).
 *
 * A closed set rather than free text, because an audit trail is only useful if
 * "show me every deletion" is a query rather than a guess at how somebody
 * phrased it that week.
 */
enum AuditAction: string
{
    case Login = 'login';
    case LoginFailed = 'login.failed';
    case Logout = 'logout';
    case LeadCreated = 'lead.created';
    case LeadUpdated = 'lead.updated';
    case LeadDeleted = 'lead.deleted';
    case LeadAssigned = 'lead.assigned';
    case ContactCreated = 'contact.created';
    case ContactUpdated = 'contact.updated';
    case ContactDeleted = 'contact.deleted';
    case CompanyCreated = 'company.created';
    case CompanyUpdated = 'company.updated';
    case CompanyDeleted = 'company.deleted';
    case DealCreated = 'deal.created';
    case DealUpdated = 'deal.updated';
    case DealDeleted = 'deal.deleted';
    case DealStageChanged = 'deal.stage_changed';
    case ApiKeyCreated = 'api_key.created';
    case ApiKeyRevoked = 'api_key.revoked';
    case WebhookCreated = 'webhook.created';
    case WebhookUpdated = 'webhook.updated';
    case WebhookDeleted = 'webhook.deleted';
    case BrandingChanged = 'branding.changed';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Signed in',
            self::LoginFailed => 'Failed sign-in',
            self::Logout => 'Signed out',
            self::LeadCreated => 'Lead created',
            self::LeadUpdated => 'Lead edited',
            self::LeadDeleted => 'Lead deleted',
            self::LeadAssigned => 'Lead assigned',
            self::ContactCreated => 'Contact created',
            self::ContactUpdated => 'Contact edited',
            self::ContactDeleted => 'Contact deleted',
            self::CompanyCreated => 'Company created',
            self::CompanyUpdated => 'Company edited',
            self::CompanyDeleted => 'Company deleted',
            self::DealCreated => 'Deal opened',
            self::DealUpdated => 'Deal edited',
            self::DealDeleted => 'Deal deleted',
            self::DealStageChanged => 'Deal stage changed',
            self::ApiKeyCreated => 'API key created',
            self::ApiKeyRevoked => 'API key revoked',
            self::WebhookCreated => 'Webhook endpoint added',
            self::WebhookUpdated => 'Webhook endpoint changed',
            self::WebhookDeleted => 'Webhook endpoint removed',
            self::BrandingChanged => 'Branding changed',
        };
    }

    /**
     * Whether this records something being destroyed.
     *
     * Used to colour the trail, because "what was deleted and by whom" is the
     * question an audit log is opened for most often.
     */
    public function isDestructive(): bool
    {
        return str_ends_with($this->value, '.deleted')
            || $this === self::ApiKeyRevoked;
    }

    public function group(): string
    {
        return match ($this) {
            self::Login, self::LoginFailed, self::Logout => 'Access',
            self::ApiKeyCreated, self::ApiKeyRevoked,
            self::WebhookCreated, self::WebhookUpdated, self::WebhookDeleted => 'Integrations',
            self::BrandingChanged => 'Settings',
            default => 'Records',
        };
    }

    /**
     * @return list<array{value: string, label: string, group: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'group' => $case->group(),
            ],
            self::cases(),
        );
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
