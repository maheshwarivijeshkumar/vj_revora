<?php

declare(strict_types=1);

namespace App\Domain\Leads\Services;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Routes a lead to an owner (§23).
 *
 * Only the two strategies that need no configuration are implemented here.
 * Rule-based routing (territory, product, score, campaign, business hours)
 * arrives with the automation engine in Phase 3, which is where conditions
 * already live; duplicating a rule evaluator now would mean two of them.
 */
final class LeadAssigner
{
    public const ROUND_ROBIN = 'round_robin';

    public const LEAST_ASSIGNED = 'least_assigned';

    public const MANUAL = 'manual';

    /**
     * Assigns a lead and records why.
     *
     * Returns null when the workspace has nobody to assign to, which is a
     * normal state for a workspace still being set up rather than an error.
     */
    public function assign(Lead $lead, string $strategy = self::LEAST_ASSIGNED, ?int $assignedBy = null): ?LeadAssignment
    {
        $user = match ($strategy) {
            self::ROUND_ROBIN => $this->roundRobin(),
            self::LEAST_ASSIGNED => $this->leastAssigned(),
            default => null,
        };

        if (! $user instanceof User) {
            return null;
        }

        return $this->assignTo($lead, $user, $strategy, $assignedBy);
    }

    /** Assigns to a specific person, bypassing strategy. */
    public function assignTo(Lead $lead, User $user, string $strategy = self::MANUAL, ?int $assignedBy = null): LeadAssignment
    {
        return DB::transaction(function () use ($lead, $user, $strategy, $assignedBy): LeadAssignment {
            $lead->owner_id = $user->id;
            $lead->save();

            return LeadAssignment::create([
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'team_id' => $lead->team_id,
                'strategy' => $strategy,
                'reason' => $this->reasonFor($strategy),
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
            ]);
        });
    }

    /**
     * Next in rotation: whoever went longest without receiving one.
     *
     * Reading the last assignment rather than holding a cursor keeps this
     * correct when people are added, removed or deactivated mid-rotation.
     */
    private function roundRobin(): ?User
    {
        return User::query()
            ->where('status', 'active')
            ->leftJoin('lead_assignments', 'lead_assignments.user_id', '=', 'users.id')
            ->groupBy('users.id')
            ->select('users.*')
            ->orderByRaw('COALESCE(MAX(lead_assignments.assigned_at), \'1970-01-01\') ASC')
            ->orderBy('users.id')
            ->first();
    }

    /** Fewest open leads currently owned. */
    private function leastAssigned(): ?User
    {
        return User::query()
            ->where('users.status', 'active')
            ->leftJoin('leads', function ($join): void {
                $join->on('leads.owner_id', '=', 'users.id')
                    ->whereNull('leads.merged_into_id')
                    ->whereNull('leads.deleted_at')
                    ->whereNotIn('leads.status', ['won', 'lost', 'archived']);
            })
            ->groupBy('users.id')
            ->select('users.*')
            ->orderByRaw('COUNT(leads.id) ASC')
            ->orderBy('users.id')
            ->first();
    }

    private function reasonFor(string $strategy): string
    {
        return match ($strategy) {
            self::ROUND_ROBIN => 'Next in rotation',
            self::LEAST_ASSIGNED => 'Fewest open leads',
            default => 'Assigned manually',
        };
    }
}
