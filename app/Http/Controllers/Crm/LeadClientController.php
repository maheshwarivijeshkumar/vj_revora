<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Operations\Actions\ConvertLeadToClient;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;

/**
 * The explicit "this won lead is now a client" action.
 *
 * Saving a lead as won already does this. The button covers a lead that was
 * won before that rule existed, or a save that did not change the status.
 */
final class LeadClientController extends Controller
{
    public function store(Lead $lead, ConvertLeadToClient $convert): RedirectResponse
    {
        if ($lead->status !== LeadStatus::Won) {
            return back()->with('error', 'Mark the lead as won before turning them into a client.');
        }

        $already = Client::query()->where('lead_id', $lead->id)->exists();
        $client = $convert->handle($lead);

        return to_route('clients.show', $client)->with(
            'success',
            $already
                ? "{$client->name} is already a client."
                : "{$client->name} is now a client. Their first project is ready for an estimate.",
        );
    }
}
