<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Silence-model sweep. Landlord-side and tenant-side both run LIVE
// post-Phase-3: send_escalation, send_nudge, transition_dormant_intent
// (now a real transition), and resume_from_hold (absorbs SweepHolds)
// all execute. --pretend-today always forces full shadow — never
// sends on either side.
//
// withoutOverlapping prevents two scheduled runs racing if a sweep
// runs long. Combined with the per-case lockForUpdate guard inside
// the command, this gives defence-in-depth against double-fire.
Schedule::command('silence:sweep')->dailyAt('06:15')->withoutOverlapping();
