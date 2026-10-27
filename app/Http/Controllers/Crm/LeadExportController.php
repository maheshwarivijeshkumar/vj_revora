<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Audit\AuditRecorder;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Leads\LeadListQuery;
use App\Domain\Leads\LeadsExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads the leads list, as currently filtered, as an Excel file (§70).
 */
final class LeadExportController extends Controller
{
    public function __invoke(Request $request, LeadListQuery $listQuery, AuditRecorder $audit): BinaryFileResponse
    {
        $query = $listQuery->build($request);

        // An export takes personal data out of the system, so who took what is
        // recorded (§54).
        $audit->record(AuditAction::LeadsExported, after: [
            'rows' => (clone $query)->count(),
            'filters' => $request->except(['page', 'per_page']),
        ]);

        return Excel::download(new LeadsExport($query), 'leads-'.now()->format('Y-m-d-His').'.xlsx');
    }
}
