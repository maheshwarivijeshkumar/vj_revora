<?php

declare(strict_types=1);

namespace App\Domain\Leads\Events;

use App\Domain\Leads\DataObjects\CaptureResult;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised once a lead has been captured, scored and routed (§90).
 *
 * Automation triggers, outbound webhooks, SLA timers, notifications and usage
 * metering all hang off this rather than off the pipeline itself, so adding a
 * consumer never means editing CaptureLead.
 */
final class LeadCaptured
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CaptureResult $result,
    ) {}
}
