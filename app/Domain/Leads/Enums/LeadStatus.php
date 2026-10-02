<?php

declare(strict_types=1);

namespace App\Domain\Leads\Enums;

/**
 * Lead lifecycle (§85, §119).
 *
 * Every transition is auditable. The order here is the order of the funnel,
 * which `stage()` exposes for reporting.
 */
enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Nurturing = 'nurturing';
    case Appointment = 'appointment';
    case Proposal = 'proposal';
    case Won = 'won';
    case Lost = 'lost';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Qualified => 'Qualified',
            self::Nurturing => 'Nurturing',
            self::Appointment => 'Appointment',
            self::Proposal => 'Proposal',
            self::Won => 'Won',
            self::Lost => 'Lost',
            self::Archived => 'Archived',
        };
    }

    /**
     * Semantic badge tone (§119). Never the only signal — the label always
     * ships alongside it.
     */
    public function tone(): string
    {
        return match ($this) {
            self::New => 'new',
            self::Contacted => 'contacted',
            self::Qualified, self::Won => 'qualified',
            self::Nurturing => 'nurturing',
            self::Appointment => 'appointment',
            self::Proposal => 'proposal',
            self::Lost => 'lost',
            self::Archived => 'archived',
        };
    }

    /** Whether the lead is still in play. */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Won, self::Lost, self::Archived], true);
    }

    /**
     * Funnel position, for conversion reporting. Terminal states sit outside
     * the ordering because they are outcomes, not stages.
     */
    public function stage(): ?int
    {
        return match ($this) {
            self::New => 1,
            self::Contacted => 2,
            self::Qualified => 3,
            self::Nurturing => 3,
            self::Appointment => 4,
            self::Proposal => 5,
            default => null,
        };
    }

    /**
     * @return list<array{value: string, label: string, tone: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'tone' => $case->tone(),
            ],
            self::cases(),
        );
    }
}
