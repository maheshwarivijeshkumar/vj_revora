<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Operations\Actions\CreateInvoiceFromEstimate;
use App\Domain\Operations\CommercialPresenter;
use App\Domain\Operations\DocumentNumber;
use App\Domain\Operations\Enums\InvoiceStatus;
use App\Domain\Operations\LineTotals;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class InvoiceController extends Controller
{
    public function index(): Response
    {
        $invoices = Invoice::query()
            ->with(['client', 'project', 'estimate'])
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Invoice $invoice): array => CommercialPresenter::invoice($invoice))
            ->all();

        return Inertia::render('crm/invoices/Index', [
            'invoices' => $invoices,
            'clients' => Client::query()->orderBy('name')->limit(200)->get(['id', 'name'])
                ->map(fn (Client $client): array => ['id' => $client->id, 'name' => $client->name])
                ->all(),
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load(['client', 'project', 'estimate']);

        return Inertia::render('crm/invoices/Show', [
            'invoice' => CommercialPresenter::invoice($invoice),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = app(TenantContext::class)->id();

        $validated = $request->validate([
            ...LineTotals::rules(),
            'client_id' => ['required', Rule::exists('clients', 'id')->where('tenant_id', $tenantId)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('tenant_id', $tenantId)],
            'due_on' => ['nullable', 'date'],
        ]);

        $projectId = $validated['project_id'] ?? null;

        if ($projectId !== null && ! Project::query()->whereKey($projectId)->where('client_id', $validated['client_id'])->exists()) {
            return back()->withErrors(['project_id' => 'That project belongs to a different client.'])->withInput();
        }

        $priced = LineTotals::calculate($validated['lines'], $validated['tax_rate'] ?? 0);

        $invoice = Invoice::query()->create([
            'client_id' => $validated['client_id'],
            'project_id' => $projectId,
            'number' => DocumentNumber::next('INV', Invoice::class),
            'title' => $validated['title'],
            'status' => InvoiceStatus::Draft,
            'lines' => $priced['lines'],
            'tax_rate' => $priced['tax_rate'],
            'subtotal' => $priced['subtotal'],
            'tax' => $priced['tax'],
            'total' => $priced['total'],
            'due_on' => $validated['due_on'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return to_route('invoices.show', $invoice)->with('success', "{$invoice->number} has been saved.");
    }

    public function fromEstimate(Estimate $estimate, CreateInvoiceFromEstimate $create): RedirectResponse
    {
        $invoice = $create->handle($estimate);

        return to_route('invoices.show', $invoice)->with('success', "{$invoice->number} was created from {$estimate->number}.");
    }

    public function send(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            return back()->with('error', 'Only a draft invoice can be sent.');
        }

        $invoice->status = InvoiceStatus::Sent;
        $invoice->issued_on ??= now()->toDateString();
        $invoice->due_on ??= now()->addDays(14)->toDateString();
        $invoice->save();

        return back()->with('success', "{$invoice->number} is marked sent.");
    }

    public function pay(Invoice $invoice): RedirectResponse
    {
        $shown = $invoice->presentedStatus();

        if (! in_array($shown, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true)) {
            return back()->with('error', 'Send the invoice before recording a payment.');
        }

        $invoice->status = InvoiceStatus::Paid;
        $invoice->paid_amount = $invoice->total;
        $invoice->paid_on = now()->toDateString();
        $invoice->save();

        return back()->with('success', "{$invoice->number} is paid.");
    }

    public function void(Invoice $invoice): RedirectResponse
    {
        if ($invoice->status === InvoiceStatus::Paid) {
            return back()->with('error', 'A paid invoice cannot be voided.');
        }

        $invoice->status = InvoiceStatus::Void;
        $invoice->save();

        return back()->with('success', "{$invoice->number} is void.");
    }
}
