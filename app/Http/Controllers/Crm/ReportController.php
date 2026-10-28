<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Operations\Enums\EstimateStatus;
use App\Domain\Operations\Enums\InvoiceStatus;
use App\Domain\Operations\Enums\ProjectStatus;
use App\Domain\Operations\Money;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The numbers a workspace needs once jobs and invoices exist.
 */
final class ReportController extends Controller
{
    public function __invoke(): Response
    {
        $paidThisMonth = (float) Invoice::query()
            ->where('status', InvoiceStatus::Paid)
            ->whereBetween('paid_on', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('total');

        $outstanding = (float) Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->sum(DB::raw('total - paid_amount'));

        $wonLeads = Lead::query()->master()->where('status', LeadStatus::Won)->count();
        $openLeads = Lead::query()->master()->open()->count();

        return Inertia::render('crm/reports/Index', [
            'cards' => [
                ['label' => 'Clients', 'value' => (string) Client::query()->count(), 'href' => '/clients'],
                ['label' => 'Active projects', 'value' => (string) Project::query()->where('status', ProjectStatus::Active)->count(), 'href' => '/projects'],
                ['label' => 'Won leads', 'value' => (string) $wonLeads, 'href' => '/leads'],
                ['label' => 'Open leads', 'value' => (string) $openLeads, 'href' => '/leads'],
                ['label' => 'Estimates waiting', 'value' => (string) Estimate::query()->where('status', EstimateStatus::Sent)->count(), 'href' => '/estimates'],
                ['label' => 'Revenue this month', 'value' => Money::display($paidThisMonth), 'href' => '/invoices'],
                ['label' => 'Outstanding', 'value' => Money::display($outstanding), 'href' => '/invoices'],
            ],
        ]);
    }
}
