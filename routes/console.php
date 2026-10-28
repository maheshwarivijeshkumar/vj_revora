<?php

use App\Console\Commands\FetchOpportunities;
use App\Console\Commands\FetchTenders;
use App\Console\Commands\PurgeDeletedDocuments;
use App\Console\Commands\RunMessaging;
use App\Console\Commands\SyncCampaigns;
use App\Console\Commands\SyncLeadAds;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Schedule
|--------------------------------------------------------------------------
*/

// Removing a document keeps the file so a mistaken delete can be undone.
// Something has to clear them up eventually, or "deleted" never means deleted
// and the disk grows forever (§88).
Schedule::command(PurgeDeletedDocuments::class)->dailyAt('03:30');

// Lead ads are polled rather than pushed: no public webhook endpoint to expose,
// and an hour is the freshness the sales team asked for. Each run overlaps the
// last, and the per-lead event id makes the overlap free (§57, §58).
Schedule::command(SyncLeadAds::class)->hourly()->withoutOverlapping();

// Campaign names follow the same hour. A failed ad-account read still keeps
// the campaigns already stored on leads.
Schedule::command(SyncCampaigns::class)->hourly()->withoutOverlapping();

// Government tenders: Pakistan PPRA, UK Find a Tender, Contracts Finder and
// Public Contracts Scotland. Twice a day, morning and afternoon Pakistan time,
// which is also mid-morning and late afternoon in the UK. A full pass pages
// the UK feeds, so it runs in the background.
Schedule::command(FetchTenders::class)
    ->twiceDaily(8, 16)
    ->timezone('Asia/Karachi')
    ->withoutOverlapping()
    ->runInBackground();

// Follow-up emails go out on the minute they are due (0h, 6h, 24h), and the
// inboxes are read every five minutes so a reply stops the sequence before
// the next email would go.
Schedule::command(RunMessaging::class)->everyMinute()->withoutOverlapping();
Schedule::command(RunMessaging::class, ['--mailboxes'])->everyFiveMinutes()->withoutOverlapping();

// Job posts. The public boards twice a day (Remotive asks for no more than a
// few calls a day); Upwork every two hours, because the first proposals on a
// fresh job are the ones that get read.
Schedule::command(FetchOpportunities::class)
    ->twiceDaily(9, 17)
    ->timezone('Asia/Karachi')
    ->withoutOverlapping()
    ->runInBackground();
Schedule::command(FetchOpportunities::class, ['--upwork'])->everyTwoHours()->withoutOverlapping()->runInBackground();
