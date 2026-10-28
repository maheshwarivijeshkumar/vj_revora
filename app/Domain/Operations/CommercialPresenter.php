<?php

declare(strict_types=1);

namespace App\Domain\Operations;

use App\Models\Estimate;
use App\Models\Invoice;

/**
 * The shape the estimate and invoice screens share.
 */
final class CommercialPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function estimate(Estimate $estimate): array
    {
        return [
            'id' => $estimate->id,
            'number' => $estimate->number,
            'title' => $estimate->title,
            'status' => $estimate->status->value,
            'status_label' => $estimate->status->label(),
            'status_tone' => $estimate->status->tone(),
            'client_id' => $estimate->client_id,
            'client' => $estimate->client?->name,
            'project_id' => $estimate->project_id,
            'project' => $estimate->project?->name,
            'lines' => $estimate->lines,
            'tax_rate' => $estimate->tax_rate,
            'subtotal' => $estimate->subtotal,
            'tax' => $estimate->tax,
            'total' => $estimate->total,
            'valid_until' => $estimate->valid_until?->toDateString(),
            'notes' => $estimate->notes,
            'invoice_id' => $estimate->invoice?->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function invoice(Invoice $invoice): array
    {
        $status = $invoice->presentedStatus();

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'title' => $invoice->title,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_tone' => $status->tone(),
            'client_id' => $invoice->client_id,
            'client' => $invoice->client?->name,
            'project' => $invoice->project?->name,
            'estimate_id' => $invoice->estimate_id,
            'estimate_number' => $invoice->estimate?->number,
            'lines' => $invoice->lines,
            'tax_rate' => $invoice->tax_rate,
            'subtotal' => $invoice->subtotal,
            'tax' => $invoice->tax,
            'total' => $invoice->total,
            'paid_amount' => $invoice->paid_amount,
            'issued_on' => $invoice->issued_on?->toDateString(),
            'due_on' => $invoice->due_on?->toDateString(),
            'paid_on' => $invoice->paid_on?->toDateString(),
            'notes' => $invoice->notes,
        ];
    }
}
