<?php

declare(strict_types=1);

namespace App\Domain\Deals\Events;

use App\Models\Deal;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A deal moved between stages (§28, §90).
 *
 * Automation triggers, stage-specific tasks and notifications, outbound
 * webhooks and revenue reporting all hang off this rather than off the move
 * action, so adding a consumer never means editing the action.
 */
final class DealStageChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Deal $deal,
        public readonly int $fromStageId,
        public readonly int $toStageId,
    ) {}
}
