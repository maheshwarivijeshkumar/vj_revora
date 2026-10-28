<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Domain\Campaigns\Actions\SyncCampaigns;
use App\Domain\Campaigns\AdPlatform;
use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Campaigns running on connected ad accounts, plus any network a lead names.
 */
final class CampaignController extends Controller
{
    public function index(Request $request): Response
    {
        $platform = $request->string('platform')->toString();
        $filter = preg_match('/^[a-z0-9-]{1,32}$/', $platform) === 1 ? $platform : '';

        $campaigns = AdCampaign::query()
            ->with('connection:id,account_name')
            ->when($filter !== '', fn ($query) => $query->where('platform', $filter))
            ->orderBy('platform')
            ->orderBy('name')
            ->get()
            ->map(fn (AdCampaign $campaign): array => [
                'id' => $campaign->id,
                'platform' => $campaign->platform,
                'platform_label' => AdPlatform::label($campaign->platform),
                'name' => $campaign->name,
                'status' => $campaign->status,
                'objective' => $campaign->objective,
                'starts_at' => $campaign->starts_at?->toDateString(),
                'ends_at' => $campaign->ends_at?->toDateString(),
                'account' => $campaign->connection?->account_name,
            ]);

        $platforms = AdCampaign::query()
            ->select('platform')
            ->distinct()
            ->orderBy('platform')
            ->pluck('platform')
            ->map(fn (string $key): array => [
                'value' => $key,
                'label' => AdPlatform::label($key),
            ])
            ->values();

        return Inertia::render('crm/campaigns/Index', [
            'campaigns' => $campaigns,
            'platforms' => $platforms,
            'filters' => ['platform' => $filter],
        ]);
    }

    public function sync(SyncCampaigns $sync): RedirectResponse
    {
        $saved = $sync->handle();

        return back()->with('success', "Updated {$saved} campaign(s).");
    }
}
