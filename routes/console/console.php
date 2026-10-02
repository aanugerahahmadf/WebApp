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

// Jaga storage/ tetap kecil: pangkas log lama + sisa temp subprocess.
// laravel.log pernah membengkak jadi 66 MB karena channel `single` tidak
// pernah dirotasi. Foto review & katalog ditangani media:prune di bawah.
Schedule::command('app:prune-storage --days=7')->dailyAt('02:30');

// Foto review & katalog menumpuk kalau ada upload yang menggantung atau data
// di luar DB -- file yang sudah tidak dirujuk tidak pernah dihapus otomatis,
// dan review-photos pernah sampai 7 GB. Bersih harian supaya tidak berulang.
Schedule::command('media:prune --apply')->dailyAt('03:15');
