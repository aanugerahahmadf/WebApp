<?php

use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    /** @var Command $this */
    echo Inspiring::quote().PHP_EOL;
})->purpose('Display an inspiring quote');

Schedule::command('app:update-order-status')->everyMinute();
Schedule::command('app:sync-bri-payments')->everyFiveMinutes();
