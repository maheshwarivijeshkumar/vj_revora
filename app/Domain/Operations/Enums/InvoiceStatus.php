<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Void => 'Void',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Sent => 'info',
            self::Paid => 'success',
            self::Overdue => 'danger',
            self::Void => 'neutral',
        };
    }
}
