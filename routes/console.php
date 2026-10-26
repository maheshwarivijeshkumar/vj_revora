<?php

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
