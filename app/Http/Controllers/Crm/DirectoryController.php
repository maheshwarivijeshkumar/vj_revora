<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\CrewMember;
use App\Models\Material;
use App\Models\Subcontractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Crew, subcontractors, and materials. Same list-and-add shape, three records.
 */
final class DirectoryController extends Controller
{
    public function crew(): Response
    {
        return Inertia::render('crm/directory/Index', [
            'kind' => 'crew',
            'title' => 'Crew',
            'intro' => 'People on your own team. A crew member can be booked on the schedule.',
            'rows' => CrewMember::query()->latest('id')->limit(200)->get()
                ->map(fn (CrewMember $member): array => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'detail' => $member->role,
                    'phone' => $member->phone,
                    'email' => $member->email,
                    'extra' => null,
                ])->all(),
            'fields' => [
                ['key' => 'name', 'label' => 'Name', 'required' => true],
                ['key' => 'role', 'label' => 'Role'],
                ['key' => 'phone', 'label' => 'Phone'],
                ['key' => 'email', 'label' => 'Email'],
            ],
        ]);
    }

    public function storeCrew(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'role' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'max:160'],
        ]);

        CrewMember::query()->create([...$validated, 'status' => 'active']);

        return back()->with('success', "{$validated['name']} has been added to the crew.");
    }

    public function destroyCrew(CrewMember $crewMember): RedirectResponse
    {
        $name = $crewMember->name;
        $crewMember->delete();

        return back()->with('success', "{$name} has been removed from the crew.");
    }

    public function subcontractors(): Response
    {
        return Inertia::render('crm/directory/Index', [
            'kind' => 'subcontractors',
            'title' => 'Subcontractors',
            'intro' => 'Outside firms you hire onto a job.',
            'rows' => Subcontractor::query()->latest('id')->limit(200)->get()
                ->map(fn (Subcontractor $firm): array => [
                    'id' => $firm->id,
                    'name' => $firm->name,
                    'detail' => $firm->trade,
                    'phone' => $firm->phone,
                    'email' => $firm->email,
                    'extra' => $firm->company,
                ])->all(),
            'fields' => [
                ['key' => 'name', 'label' => 'Contact name', 'required' => true],
                ['key' => 'company', 'label' => 'Company'],
                ['key' => 'trade', 'label' => 'Trade'],
                ['key' => 'phone', 'label' => 'Phone'],
                ['key' => 'email', 'label' => 'Email'],
            ],
        ]);
    }

    public function storeSubcontractor(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'company' => ['nullable', 'string', 'max:160'],
            'trade' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'max:160'],
        ]);

        Subcontractor::query()->create([...$validated, 'status' => 'active']);

        return back()->with('success', "{$validated['name']} has been added.");
    }

    public function destroySubcontractor(Subcontractor $subcontractor): RedirectResponse
    {
        $name = $subcontractor->name;
        $subcontractor->delete();

        return back()->with('success', "{$name} has been removed.");
    }

    public function materials(): Response
    {
        return Inertia::render('crm/directory/Index', [
            'kind' => 'materials',
            'title' => 'Materials',
            'intro' => 'What you keep on hand, and what it costs.',
            'rows' => Material::query()->orderBy('name')->limit(200)->get()
                ->map(fn (Material $material): array => [
                    'id' => $material->id,
                    'name' => $material->name,
                    'detail' => $material->unit,
                    'phone' => null,
                    'email' => null,
                    'extra' => $material->quantity.' @ '.$material->unit_cost.($material->supplier ? ' · '.$material->supplier : ''),
                ])->all(),
            'fields' => [
                ['key' => 'name', 'label' => 'Name', 'required' => true],
                ['key' => 'unit', 'label' => 'Unit'],
                ['key' => 'quantity', 'label' => 'Quantity', 'type' => 'number'],
                ['key' => 'unit_cost', 'label' => 'Unit cost', 'type' => 'number'],
                ['key' => 'supplier', 'label' => 'Supplier'],
            ],
        ]);
    }

    public function storeMaterial(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'unit' => ['nullable', 'string', 'max:40'],
            'quantity' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'supplier' => ['nullable', 'string', 'max:160'],
        ]);

        Material::query()->create([
            'name' => $validated['name'],
            'unit' => $validated['unit'] ?: 'each',
            'quantity' => $validated['quantity'] ?? 0,
            'unit_cost' => $validated['unit_cost'] ?? 0,
            'supplier' => $validated['supplier'] ?? null,
        ]);

        return back()->with('success', "{$validated['name']} has been added.");
    }

    public function destroyMaterial(Material $material): RedirectResponse
    {
        $name = $material->name;
        $material->delete();

        return back()->with('success', "{$name} has been removed.");
    }
}
