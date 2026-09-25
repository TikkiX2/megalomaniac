<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('integrations:expire-approvals')->everyTenMinutes();
Schedule::command('integrations:prune-activity')->daily();
Schedule::command('agents:dispatch-due')->everyMinute();
