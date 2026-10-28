<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Operations\DocumentNumber;
use App\Domain\Operations\Enums\EstimateStatus;
use App\Domain\Operations\Enums\InvoiceStatus;
use App\Models\Estimate;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Copies an accepted estimate into an invoice for the same client.
 */
final class CreateInvoiceFromEstimate
{
    public function handle(Estimate $estimate): Invoice
    {
        if ($estimate->status !== EstimateStatus::Accepted) {
            throw ValidationException::withMessages([
                'estimate' => 'Accept the estimate before creating an invoice.',
            ]);
        }

        $existing = Invoice::query()->where('estimate_id', $estimate->id)->first();

        if ($existing instanceof Invoice) {
            return $existing;
        }

        return DB::transaction(function () use ($estimate): Invoice {
            $again = Invoice::query()->where('estimate_id', $estimate->id)->lockForUpdate()->first();

            if ($again instanceof Invoice) {
                return $again;
            }

            return Invoice::query()->create([
                'client_id' => $estimate->client_id,
                'estimate_id' => $estimate->id,
                'project_id' => $estimate->project_id,
                'number' => DocumentNumber::next('INV', Invoice::class),
                'title' => $estimate->title,
                'status' => InvoiceStatus::Draft,
                'lines' => $estimate->lines,
                'tax_rate' => $estimate->tax_rate,
                'subtotal' => $estimate->subtotal,
                'tax' => $estimate->tax,
                'total' => $estimate->total,
                'notes' => $estimate->notes,
            ]);
        });
    }
}
