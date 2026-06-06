<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('cases:sweep-holds')->dailyAt('06:00');
Schedule::command('cases:sweep-escalations')->dailyAt('06:05');
Schedule::command('cases:sweep-dormancy')->dailyAt('06:10');

// Silence-model shadow sweep (Phase 2a). Logs intended actions only —
// no sends, no transitions. Runs alongside the old sweeps but writes
// to a separate table (silence_shadow_log). 2b will swap this to live
// and delete the three sweeps above.
Schedule::command('silence:sweep')->dailyAt('06:15');
