<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\ChangeOrder;
use App\Models\Client;
use App\Models\CrewMember;
use App\Models\Document;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Material;
use App\Models\Project;
use App\Models\ScheduleEntry;
use App\Models\Subcontractor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Deletes the records a person already confirmed in the list.
 *
 * The page does not call this until the confirmation dialog is accepted.
 * Tenant scope limits the delete to the current workspace, so another
 * workspace's ids are skipped.
 */
final class OperationsBulkController extends Controller
{
    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'resource' => ['required', 'string'],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        [$model, $permission] = $this->resource($validated['resource']);

        abort_unless($request->user()?->can($permission) ?? false, 403);

        $deleted = $model::query()->whereKey($validated['ids'])->delete();

        if ($deleted === 0) {
            return back()->with('error', 'Nothing was deleted.');
        }

        return back()->with(
            'success',
            $deleted === 1 ? '1 record deleted.' : "{$deleted} records deleted.",
        );
    }

    /**
     * @return array{0: class-string<Model>, 1: string}
     */
    private function resource(string $name): array
    {
        return match ($name) {
            'clients' => [Client::class, 'company.delete'],
            'projects' => [Project::class, 'company.delete'],
            'estimates' => [Estimate::class, 'quote.delete'],
            'invoices' => [Invoice::class, 'billing.manage'],
            'change-orders' => [ChangeOrder::class, 'quote.delete'],
            'schedule' => [ScheduleEntry::class, 'task.delete'],
            'crew' => [CrewMember::class, 'company.delete'],
            'subcontractors' => [Subcontractor::class, 'company.delete'],
            'materials' => [Material::class, 'product.delete'],
            'documents' => [Document::class, 'document.delete'],
            default => abort(404),
        };
    }
}
