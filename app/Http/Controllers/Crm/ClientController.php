<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Operations\Enums\ClientStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customers. A won lead becomes one; a person can also be added here.
 */
final class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $clients = Client::query()
            ->withCount(['projects', 'invoices'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->name,
                'company_name' => $client->company_name,
                'email' => $client->email,
                'phone' => $client->phone,
                'status' => $client->status->value,
                'status_label' => $client->status->label(),
                'status_tone' => $client->status->tone(),
                'projects_count' => $client->projects_count,
                'invoices_count' => $client->invoices_count,
                'lead_id' => $client->lead_id,
            ])
            ->all();

        return Inertia::render('crm/clients/Index', [
            'clients' => $clients,
            'filters' => ['search' => $search],
        ]);
    }

    public function show(Client $client): Response
    {
        $client->load([
            'lead',
            'projects',
            'estimates.invoice',
            'invoices',
        ]);

        return Inertia::render('crm/clients/Show', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'company_name' => $client->company_name,
                'notes' => $client->notes,
                'status' => $client->status->value,
                'status_label' => $client->status->label(),
                'status_tone' => $client->status->tone(),
                'lead_id' => $client->lead_id,
                'lead_name' => $client->lead?->displayName(),
            ],
            'projects' => $client->projects->map(fn ($project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status->value,
                'status_label' => $project->status->label(),
                'status_tone' => $project->status->tone(),
            ])->all(),
            'estimates' => $client->estimates->map(fn ($estimate): array => [
                'id' => $estimate->id,
                'number' => $estimate->number,
                'title' => $estimate->title,
                'status_label' => $estimate->status->label(),
                'status_tone' => $estimate->status->tone(),
                'total' => $estimate->total,
                'invoice_id' => $estimate->invoice?->id,
            ])->all(),
            'invoices' => $client->invoices->map(fn ($invoice): array => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'title' => $invoice->title,
                'status_label' => $invoice->presentedStatus()->label(),
                'status_tone' => $invoice->presentedStatus()->tone(),
                'total' => $invoice->total,
            ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $client = Client::query()->create([
            ...$validated,
            'status' => ClientStatus::Active,
            'converted_at' => now(),
        ]);

        return to_route('clients.show', $client)->with('success', "{$client->name} is now a client.");
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(ClientStatus::class)],
        ]);

        $client->fill($validated);
        $client->save();

        return back()->with('success', "{$client->name} has been updated.");
    }
}
