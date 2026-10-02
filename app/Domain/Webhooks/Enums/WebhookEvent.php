<?php

declare(strict_types=1);

namespace App\Domain\Webhooks\Enums;

/**
 * The events a workspace can subscribe to (§49).
 *
 * The full list is named here so the vocabulary is stable and a subscriber's
 * stored selection never has to be migrated. `isAvailable()` is what decides
 * whether one can be subscribed to today — see ApiScope for the same
 * reasoning: offering a subscription that will never fire is a claim the
 * product does not honour (§124).
 */
enum WebhookEvent: string
{
    case LeadCreated = 'lead.created';
    case LeadUpdated = 'lead.updated';
    case LeadQualified = 'lead.qualified';
    case LeadAssigned = 'lead.assigned';
    case LeadConverted = 'lead.converted';
    case ContactCreated = 'contact.created';
    case DealCreated = 'deal.created';
    case DealWon = 'deal.won';
    case DealLost = 'deal.lost';
    case MessageReceived = 'message.received';
    case MessageSent = 'message.sent';
    case AppointmentCreated = 'appointment.created';
    case AppointmentCancelled = 'appointment.cancelled';
    case AutomationCompleted = 'automation.completed';
    case AutomationFailed = 'automation.failed';
    case SubscriptionUpdated = 'subscription.updated';

    public function label(): string
    {
        return match ($this) {
            self::LeadCreated => 'Lead created',
            self::LeadUpdated => 'Lead updated',
            self::LeadQualified => 'Lead qualified',
            self::LeadAssigned => 'Lead assigned',
            self::LeadConverted => 'Lead converted to contact',
            self::ContactCreated => 'Contact created',
            self::DealCreated => 'Deal opened',
            self::DealWon => 'Deal won',
            self::DealLost => 'Deal lost',
            self::MessageReceived => 'Message received',
            self::MessageSent => 'Message sent',
            self::AppointmentCreated => 'Appointment booked',
            self::AppointmentCancelled => 'Appointment cancelled',
            self::AutomationCompleted => 'Automation completed',
            self::AutomationFailed => 'Automation failed',
            self::SubscriptionUpdated => 'Subscription changed',
        };
    }

    /**
     * The entity carried in the payload, used to group the picker.
     */
    public function group(): string
    {
        return ucfirst(explode('.', $this->value)[0]);
    }

    /**
     * Whether something in the application emits this yet.
     *
     * Messaging, appointments, automations and billing changes arrive in later
     * phases. Until they do, a subscription to them would sit silent and look
     * like a broken integration.
     */
    public function isAvailable(): bool
    {
        return match ($this) {
            self::LeadCreated,
            self::LeadUpdated,
            self::LeadQualified,
            self::LeadAssigned,
            self::ContactCreated,
            self::DealCreated,
            self::DealWon,
            self::DealLost => true,
            default => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function subscribable(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case): bool => $case->isAvailable(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function subscribableValues(): array
    {
        return array_map(fn (self $case): string => $case->value, self::subscribable());
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
            self::subscribable(),
        );
    }
}
