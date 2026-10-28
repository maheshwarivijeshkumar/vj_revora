<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Operations\CommercialPresenter;
use App\Domain\Operations\DocumentNumber;
use App\Domain\Operations\Enums\EstimateStatus;
use App\Domain\Operations\LineTotals;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class EstimateController extends Controller
{
    public function index(): Response
    {
        $estimates = Estimate::query()
            ->with(['client', 'project', 'invoice'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Estimate $estimate): array => CommercialPresenter::estimate($estimate))
            ->all();

        return Inertia::render('crm/estimates/Index', [
            'estimates' => $estimates,
            'clients' => $this->clients(),
        ]);
    }

    public function show(Estimate $estimate): Response
    {
        $estimate->load(['client', 'project', 'invoice']);

        return Inertia::render('crm/estimates/Show', [
            'estimate' => CommercialPresenter::estimate($estimate),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = app(TenantContext::class)->id();

        $validated = $request->validate([
            ...LineTotals::rules(),
            'client_id' => ['required', Rule::exists('clients', 'id')->where('tenant_id', $tenantId)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('tenant_id', $tenantId)],
            'valid_until' => ['nullable', 'date'],
        ]);

        $projectId = $validated['project_id'] ?? null;

        if ($projectId !== null && ! Project::query()->whereKey($projectId)->where('client_id', $validated['client_id'])->exists()) {
            return back()->withErrors(['project_id' => 'That project belongs to a different client.'])->withInput();
        }

        $priced = LineTotals::calculate($validated['lines'], $validated['tax_rate'] ?? 0);

        $estimate = Estimate::query()->create([
            'client_id' => $validated['client_id'],
            'project_id' => $projectId,
            'number' => DocumentNumber::next('EST', Estimate::class),
            'title' => $validated['title'],
            'status' => EstimateStatus::Draft,
            'lines' => $priced['lines'],
            'tax_rate' => $priced['tax_rate'],
            'subtotal' => $priced['subtotal'],
            'tax' => $priced['tax'],
            'total' => $priced['total'],
            'valid_until' => $validated['valid_until'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return to_route('estimates.show', $estimate)->with('success', "{$estimate->number} has been saved.");
    }

    public function send(Estimate $estimate): RedirectResponse
    {
        if ($estimate->status !== EstimateStatus::Draft) {
            return back()->with('error', 'Only a draft estimate can be marked sent.');
        }

        $estimate->status = EstimateStatus::Sent;
        $estimate->save();

        return back()->with('success', "{$estimate->number} is marked sent.");
    }

    public function accept(Estimate $estimate): RedirectResponse
    {
        if (! in_array($estimate->status, [EstimateStatus::Draft, EstimateStatus::Sent], true)) {
            return back()->with('error', 'This estimate can no longer be accepted.');
        }

        $estimate->status = EstimateStatus::Accepted;
        $estimate->save();

        return back()->with('success', "{$estimate->number} is accepted. You can invoice it now.");
    }

    public function decline(Estimate $estimate): RedirectResponse
    {
        if ($estimate->status === EstimateStatus::Accepted && $estimate->invoice()->exists()) {
            return back()->with('error', 'An invoiced estimate cannot be declined.');
        }

        $estimate->status = EstimateStatus::Declined;
        $estimate->save();

        return back()->with('success', "{$estimate->number} is declined.");
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function clients(): array
    {
        return Client::query()
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name'])
            ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])
            ->all();
    }
}
