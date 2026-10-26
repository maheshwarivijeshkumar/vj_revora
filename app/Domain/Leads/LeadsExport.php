<?php

declare(strict_types=1);

namespace App\Domain\Leads;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * The leads list as a spreadsheet (§70).
 *
 * Built from the same query as the table, so it holds exactly the filtered
 * rows; read in chunks by the package, so a large list does not load at once.
 *
 * @implements WithMapping<Lead>
 */
final readonly class LeadsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    /**
     * @param  Builder<Lead>  $query
     */
    public function __construct(
        private Builder $query,
    ) {}

    /**
     * @return Builder<Lead>
     */
    public function query(): Builder
    {
        return $this->query->with(['owner:id,name', 'source:id,name']);
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Name', 'Email', 'Phone', 'Company', 'Job title', 'Country',
            'Status', 'Score', 'Verification', 'Owner', 'Source',
            'Platform', 'Campaign', 'Ad', 'Form',
            'Created', 'Last activity',
        ];
    }

    /**
     * @param  Lead  $lead
     * @return list<string|int|null>
     */
    public function map($lead): array
    {
        $utm = $lead->utm ?? [];
        $metadata = $lead->metadata ?? [];

        return [
            $lead->displayName(),
            $lead->email,
            $lead->phone,
            $lead->company_name,
            $lead->job_title,
            $lead->country,
            $lead->status->label(),
            $lead->score,
            $lead->verification_status->label(),
            $lead->owner?->name,
            $lead->source?->name,
            $metadata['lead_ads_platform'] ?? ($utm['utm_source'] ?? null),
            $utm['utm_campaign'] ?? null,
            $utm['utm_content'] ?? null,
            $metadata['lead_ads_form'] ?? null,
            $lead->created_at?->format('Y-m-d H:i'),
            $lead->last_activity_at?->format('Y-m-d H:i'),
        ];
    }
}
