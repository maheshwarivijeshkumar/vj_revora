<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Enums;

enum MessageDirection: string
{
    case Outbound = 'outbound';
    case Inbound = 'inbound';
}
