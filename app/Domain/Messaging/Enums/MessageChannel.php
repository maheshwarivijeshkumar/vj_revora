<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Enums;

enum MessageChannel: string
{
    case Email = 'email';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp',
        };
    }
}
