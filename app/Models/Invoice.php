<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Operations\Enums\InvoiceStatus;
use App\Domain\Tenancy\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money owed by a client, either typed in or copied from an estimate.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $client_id
 * @property int|null $estimate_id
 * @property int|null $project_id
 * @property string $number
 * @property string $title
 * @property InvoiceStatus $status
 * @property list<array{description: string, quantity: string, unit_price: string, amount: string}> $lines
 * @property string $tax_rate
 * @property string $subtotal
 * @property string $tax
 * @property string $total
 * @property string $paid_amount
 * @property CarbonInterface|null $issued_on
 * @property CarbonInterface|null $due_on
 * @property CarbonInterface|null $paid_on
 * @property string|null $notes
 */
final class Invoice extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $guarded = [];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Estimate, $this> */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * A sent invoice past its due date is overdue even before anyone edits it.
     */
    public function presentedStatus(): InvoiceStatus
    {
        if ($this->status === InvoiceStatus::Sent && $this->due_on !== null && $this->due_on->copy()->endOfDay()->isPast()) {
            return InvoiceStatus::Overdue;
        }

        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'lines' => 'array',
            'tax_rate' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'issued_on' => 'date',
            'due_on' => 'date',
            'paid_on' => 'date',
        ];
    }
}
