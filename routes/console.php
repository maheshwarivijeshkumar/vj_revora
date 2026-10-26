<?php

use App\Console\Commands\FetchTenders;
use App\Console\Commands\SyncLeadAds;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Lead ads are polled rather than pushed: no public webhook endpoint to expose,
// and an hour is the freshness the sales team asked for. Each run overlaps the
// last, and the per-lead event id makes the overlap free (§57, §58).
Schedule::command(SyncLeadAds::class)->hourly()->withoutOverlapping();

// Government of Pakistan tenders (PPRA). Twice a day, morning and afternoon
// Pakistan time, which catches each day's new notices well before anyone has
// to act on them. Runs in the background: a full pass is ~40 pages.
Schedule::command(FetchTenders::class)
    ->twiceDaily(8, 16)
    ->timezone('Asia/Karachi')
    ->withoutOverlapping()
    ->runInBackground();
