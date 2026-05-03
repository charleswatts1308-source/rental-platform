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
