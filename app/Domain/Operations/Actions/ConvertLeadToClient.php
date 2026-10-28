<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Domain\Leads\Enums\LeadStatus;
use App\Domain\Operations\Enums\ClientStatus;
use App\Domain\Operations\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a won lead into a client and opens their first project.
 *
 * Calling it again returns the client that already exists, so a second save
 * of a won lead, or a won deal on a lead that was already converted, does
 * not create a second customer.
 */
final class ConvertLeadToClient
{
    public function handle(Lead $lead): Client
    {
        if ($lead->isMerged()) {
            throw ValidationException::withMessages([
                'lead' => 'A merged lead cannot become a client.',
            ]);
        }

        if ($lead->status !== LeadStatus::Won) {
            throw ValidationException::withMessages([
                'lead' => 'Mark the lead as won before turning them into a client.',
            ]);
        }

        $existing = Client::query()->where('lead_id', $lead->id)->first();

        if ($existing instanceof Client) {
            return $existing;
        }

        return DB::transaction(function () use ($lead): Client {
            $again = Client::query()->where('lead_id', $lead->id)->lockForUpdate()->first();

            if ($again instanceof Client) {
                return $again;
            }

            $client = Client::query()->create([
                'lead_id' => $lead->id,
                'name' => $lead->displayName(),
                'email' => $lead->email,
                'phone' => $lead->phone,
                'company_name' => $lead->company_name,
                'status' => ClientStatus::Active,
                'converted_at' => now(),
            ]);

            Project::query()->create([
                'client_id' => $client->id,
                'name' => trim(($lead->company_name ?: $lead->displayName()).' project'),
                'status' => ProjectStatus::Planning,
            ]);

            if ($lead->converted_at === null) {
                $lead->converted_at = now();
                $lead->save();
            }

            return $client;
        });
    }
}
