<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the hard delete expired groups command to run daily
Schedule::command('groups:hard-delete-expired')->daily();

// Schedule the BGG plays sync command to run daily at 4 AM
Schedule::command('bgg:sync-plays')->dailyAt('04:00');
