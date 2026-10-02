<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditAction;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Model;

/**
 * Records create, edit and delete against the audited entities (§54).
 *
 * One observer for all of them rather than four near-identical ones: the only
 * thing that differs is which action name a model maps to, and duplicating the
 * logic is how one entity ends up quietly unaudited.
 */
final class AuditObserver
{
    /**
     * @var array<class-string<Model>, array{created: AuditAction, updated: AuditAction, deleted: AuditAction}>
     */
    private const ACTIONS = [
        Lead::class => [
            'created' => AuditAction::LeadCreated,
            'updated' => AuditAction::LeadUpdated,
            'deleted' => AuditAction::LeadDeleted,
        ],
        Contact::class => [
            'created' => AuditAction::ContactCreated,
            'updated' => AuditAction::ContactUpdated,
            'deleted' => AuditAction::ContactDeleted,
        ],
        Company::class => [
            'created' => AuditAction::CompanyCreated,
            'updated' => AuditAction::CompanyUpdated,
            'deleted' => AuditAction::CompanyDeleted,
        ],
        Deal::class => [
            'created' => AuditAction::DealCreated,
            'updated' => AuditAction::DealUpdated,
            'deleted' => AuditAction::DealDeleted,
        ],
    ];

    public function __construct(
        private readonly AuditRecorder $recorder,
    ) {}

    public function created(Model $model): void
    {
        $action = $this->action($model, 'created');

        if ($action === null) {
            return;
        }

        // The created snapshot goes in `after` with nothing in `before`, which
        // is what distinguishes a creation from an edit when reading the trail.
        $this->recorder->record($action, $model, null, $model->attributesToArray());
    }

    public function updated(Model $model): void
    {
        $action = $this->action($model, 'updated');

        if ($action === null || $this->isMidPipeline($model)) {
            return;
        }

        $changed = $model->getChanges();
        unset($changed['updated_at']);

        // A save that changed nothing of substance is not an edit. Recording it
        // would bury the real edits under touches.
        if ($changed === []) {
            return;
        }

        $this->recorder->recordChange($action, $model);
    }

    public function deleted(Model $model): void
    {
        $action = $this->action($model, 'deleted');

        if ($action === null) {
            return;
        }

        // The whole record, not a diff: "what was in the thing that is now gone"
        // is the question a deletion audit exists to answer.
        $this->recorder->record($action, $model, $model->attributesToArray(), null);
    }

    /**
     * Whether this save is part of creating the record rather than editing it.
     *
     * The lead capture pipeline saves two or three times while scoring and
     * routing a new lead; auditing those would follow every lead.created with
     * system-authored edits nobody made.
     */
    private function isMidPipeline(Model $model): bool
    {
        return $model instanceof Lead && $model->isBeingCaptured;
    }

    private function action(Model $model, string $event): ?AuditAction
    {
        return self::ACTIONS[$model::class][$event] ?? null;
    }
}
