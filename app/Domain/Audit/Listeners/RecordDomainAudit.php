<?php

declare(strict_types=1);

namespace App\Domain\Audit\Listeners;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Deals\Events\DealStageChanged;
use App\Domain\Leads\Events\LeadCaptured;
use App\Models\PipelineStage;

/**
 * Records the two changes that matter more than the field they touch (§54).
 *
 * Assignment and a stage move are both single-column updates, so the generic
 * entity audit would file them as "lead edited" and "deal edited". Who a lead
 * was handed to, and when a deal moved, are the questions this trail is read
 * for, so they get their own actions.
 */
final class RecordDomainAudit
{
    public function __construct(
        private readonly AuditRecorder $recorder,
    ) {}

    public function handleLeadCaptured(LeadCaptured $event): void
    {
        if ($event->result->assignedTo === null) {
            return;
        }

        $this->recorder->record(
            AuditAction::LeadAssigned,
            $event->result->lead,
            after: ['owner_id' => $event->result->assignedTo],
        );
    }

    public function handleDealStageChanged(DealStageChanged $event): void
    {
        $stages = PipelineStage::query()
            ->whereKey([$event->fromStageId, $event->toStageId])
            ->get()
            ->keyBy('id');

        // Names, not ids: an audit row read six months later must still make
        // sense to someone who does not have the pipeline open beside it.
        $this->recorder->record(
            AuditAction::DealStageChanged,
            $event->deal,
            before: ['stage' => $stages->get($event->fromStageId)?->name],
            after: ['stage' => $stages->get($event->toStageId)?->name],
        );
    }
}
