<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Bulk\Actions\RunBulkLeadAction;
use App\Domain\Bulk\Enums\BulkAction;
use App\Domain\Bulk\Enums\BulkStatus;
use App\Domain\Leads\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessBulkOperation;
use App\Models\BulkOperation;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Bulk actions on leads (§113).
 *
 * Every action is permission-checked for what it actually does rather than for
 * "bulk": someone who may reassign leads is not thereby allowed to delete them.
 */
final class BulkLeadController extends Controller
{
    public function store(Request $request, RunBulkLeadAction $runner): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::enum(BulkAction::class)],
            'ids' => ['required', 'array', 'min:1', 'max:'.BulkOperation::MAX_IDS],
            'ids.*' => ['integer'],
            'owner_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'tag' => ['nullable', 'string', 'max:60'],
        ], [
            'ids.max' => 'That is more leads than one action can take. Narrow the selection first.',
        ]);

        $action = BulkAction::from($validated['action']);

        $user = $request->user();

        // Checked here rather than on the route, because which permission is
        // needed depends on the action in the body.
        if ($user === null || ! $user->can($action->permission('lead'))) {
            abort(403, "You do not have permission to {$action->label()} leads.");
        }

        $this->requireArguments($action, $validated);

        // Re-resolved through the tenant scope: ids arrive from a client, and an
        // id from another workspace must not even be counted in the total.
        $ids = Lead::query()
            ->whereIn('id', $validated['ids'])
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return back()->with('error', 'None of those leads are in this workspace any more.');
        }

        $operation = BulkOperation::create([
            'user_id' => $user->id,
            'entity' => 'lead',
            'action' => $action,
            'ids' => $ids,
            'payload' => array_filter([
                'owner_id' => $validated['owner_id'] ?? null,
                'status' => $validated['status'] ?? null,
                'tag' => $validated['tag'] ?? null,
            ], fn (mixed $value): bool => $value !== null),
            'status' => BulkStatus::Pending,
            'total' => count($ids),
        ]);

        // Small selections run in the request: the user sees the result
        // immediately rather than a progress bar for something that took 80ms.
        if (count($ids) <= BulkOperation::INLINE_LIMIT) {
            $runner->handle($operation);

            return back()->with(
                $operation->failed > 0 ? 'warning' : 'success',
                $operation->summary(),
            );
        }

        ProcessBulkOperation::dispatch($operation->id);

        // The uuid, so the page can poll this one operation rather than guessing
        // which of several is theirs.
        return back()->with('bulkOperation', [
            'id' => $operation->uuid,
            'total' => $operation->total,
            'action' => $action->label(),
        ]);
    }

    /**
     * Progress for a running operation (§113).
     */
    public function show(BulkOperation $operation): JsonResponse
    {
        return response()->json([
            'id' => $operation->uuid,
            'status' => $operation->status->value,
            'status_label' => $operation->status->label(),
            'total' => $operation->total,
            'processed' => $operation->processed,
            'failed' => $operation->failed,
            'progress' => $operation->progress(),
            'finished' => $operation->status->isFinished(),
            'summary' => $operation->status->isFinished() ? $operation->summary() : null,
            'errors' => $operation->errors,
        ]);
    }

    /**
     * An action's own arguments are required, and saying which one is missing is
     * what §59 means by an actionable error.
     *
     * @param  array<string, mixed>  $validated
     */
    private function requireArguments(BulkAction $action, array $validated): void
    {
        $missing = match ($action) {
            // Null is meaningful here — it unassigns — so presence of the key is
            // what matters, not whether it holds a value.
            BulkAction::Assign => ! array_key_exists('owner_id', $validated),
            BulkAction::ChangeStatus => ($validated['status'] ?? null) === null,
            BulkAction::AddTag, BulkAction::RemoveTag => ($validated['tag'] ?? null) === null,
            BulkAction::Delete => false,
        };

        if (! $missing) {
            return;
        }

        $field = match ($action) {
            BulkAction::Assign => 'owner_id',
            BulkAction::ChangeStatus => 'status',
            default => 'tag',
        };

        throw ValidationException::withMessages([
            $field => "Choose a value to {$action->label()} with.",
        ]);
    }
}
