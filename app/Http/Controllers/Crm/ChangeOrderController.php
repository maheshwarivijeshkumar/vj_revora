<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Operations\DocumentNumber;
use App\Domain\Operations\Enums\ChangeOrderStatus;
use App\Domain\Operations\Money;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\ChangeOrder;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class ChangeOrderController extends Controller
{
    public function index(): Response
    {
        $orders = ChangeOrder::query()
            ->with('project.client')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (ChangeOrder $order): array => $this->row($order))
            ->all();

        return Inertia::render('crm/change-orders/Index', [
            'orders' => $orders,
            'projects' => Project::query()->with('client')->orderBy('name')->limit(200)->get()
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client' => $project->client?->name,
                ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', Rule::exists('projects', 'id')->where('tenant_id', app(TenantContext::class)->id())],
            'title' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = ChangeOrder::query()->create([
            'project_id' => $validated['project_id'],
            'number' => DocumentNumber::next('CO', ChangeOrder::class),
            'title' => $validated['title'],
            'amount' => Money::of($validated['amount']),
            'reason' => $validated['reason'] ?? null,
            'status' => ChangeOrderStatus::Draft,
        ]);

        return back()->with('success', "{$order->number} has been added.");
    }

    public function update(Request $request, ChangeOrder $changeOrder): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(ChangeOrderStatus::class)],
        ]);

        $changeOrder->status = ChangeOrderStatus::from($validated['status']);
        $changeOrder->save();

        return back()->with('success', "{$changeOrder->number} is now {$changeOrder->status->label()}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ChangeOrder $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'title' => $order->title,
            'amount' => $order->amount,
            'reason' => $order->reason,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'status_tone' => $order->status->tone(),
            'project_id' => $order->project_id,
            'project' => $order->project?->name,
            'client' => $order->project?->client?->name,
        ];
    }
}
