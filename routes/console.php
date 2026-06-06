<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('cases:sweep-holds')->dailyAt('06:00');
Schedule::command('cases:sweep-dormancy')->dailyAt('06:10');

// Silence-model sweep. Landlord-side runs LIVE post-2b (the
// landlord-side cutover): send_escalation verdicts fire real letters.
// Tenant-side remains shadow until Phase 3 (nudges/dormancy go-live
// when the tenant reply UI exists). --pretend-today always forces full
// shadow — never sends on either side.
//
// withoutOverlapping prevents two scheduled runs racing if a sweep
// runs long. Combined with the per-case lockForUpdate guard inside
// the command, this gives defence-in-depth against double-send.
Schedule::command('silence:sweep')->dailyAt('06:15')->withoutOverlapping();
