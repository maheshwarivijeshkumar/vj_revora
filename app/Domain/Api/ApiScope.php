<?php

declare(strict_types=1);

namespace App\Domain\Api;

/**
 * API key scopes (§48).
 *
 * A key carries exactly the access an integration needs. The read/write split
 * matters: most integrations only pull data, and a compromised read key
 * cannot alter a workspace.
 */
enum ApiScope: string
{
    case LeadsRead = 'leads.read';
    case LeadsWrite = 'leads.write';
    case ContactsRead = 'contacts.read';
    case ContactsWrite = 'contacts.write';
    case CompaniesRead = 'companies.read';
    case CompaniesWrite = 'companies.write';
    case DealsRead = 'deals.read';
    case DealsWrite = 'deals.write';
    case MessagesSend = 'messages.send';
    case AppointmentsWrite = 'appointments.write';
    case WebhooksManage = 'webhooks.manage';
    case UsageRead = 'usage.read';

    public function label(): string
    {
        return match ($this) {
            self::LeadsRead => 'Read leads',
            self::LeadsWrite => 'Create and update leads',
            self::ContactsRead => 'Read contacts',
            self::ContactsWrite => 'Create and update contacts',
            self::CompaniesRead => 'Read companies',
            self::CompaniesWrite => 'Create and update companies',
            self::DealsRead => 'Read deals',
            self::DealsWrite => 'Create and update deals',
            self::MessagesSend => 'Send messages',
            self::AppointmentsWrite => 'Create appointments',
            self::WebhooksManage => 'Manage webhooks',
            self::UsageRead => 'Read usage',
        };
    }

    /**
     * Whether holding this scope also grants another.
     *
     * A write scope implies its read counterpart: an integration that can
     * create a lead can obviously see the one it just created, and making
     * callers request both is friction with no security benefit.
     */
    public function implies(self $other): bool
    {
        if ($this === $other) {
            return true;
        }

        return match ($this) {
            self::LeadsWrite => $other === self::LeadsRead,
            self::ContactsWrite => $other === self::ContactsRead,
            self::CompaniesWrite => $other === self::CompaniesRead,
            self::DealsWrite => $other === self::DealsRead,
            default => false,
        };
    }

    /**
     * Whether an endpoint behind this scope exists yet.
     *
     * The enum lists the full planned surface so the vocabulary is stable, but
     * offering a key holder a scope that grants access to nothing is a claim
     * the product does not honour (§124). These become grantable with the
     * phases that build them: webhooks in 1.15, messaging and appointments in
     * Phase 2.
     */
    public function isAvailable(): bool
    {
        return match ($this) {
            self::MessagesSend, self::AppointmentsWrite, self::WebhooksManage => false,
            default => true,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::grantable(),
        );
    }

    /**
     * The scopes a key may actually be granted today.
     *
     * @return list<self>
     */
    public static function grantable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case): bool => $case->isAvailable(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function grantableValues(): array
    {
        return array_map(fn (self $case): string => $case->value, self::grantable());
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
