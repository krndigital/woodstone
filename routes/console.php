<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily database backup at 02:30 Nepal time (step 15). Needs the scheduler cron on the server.
Schedule::command('woodstone:backup')->dailyAt('02:30')->timezone('Asia/Kathmandu')->withoutOverlapping()->onOneServer();
