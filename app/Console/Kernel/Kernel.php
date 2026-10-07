<?php

namespace App\Console\Kernel;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Bersihkan log & file temp setiap hari jam 02:00
        // Menghapus log > 7 hari, file temp yatim (sf_proc_*, serve.*)
        $schedule->command('app:prune-storage --apply --days=7')
            ->dailyAt('02:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/scheduler-prune-storage.log'));

        // Bersihkan file media yatim (review-photos/, Products/, Packages/)
        // yang tidak dirujuk review/tabel media — mingguan hari Minggu jam 03:00
        $schedule->command('media:prune --apply')
            ->weeklyOn(0, '03:00') // 0 = Sunday
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/scheduler-prune-media.log'));

        // Sync upload ke Firebase Storage — harian jam 04:00
        // Upload Packages, Products, review-photos ke Firebase (GCS)
        // Hapus lokal setelah upload (--delete-local) untuk free storage lokal
        $schedule->command('firebase:sync --apply --delete-local')
            ->dailyAt('04:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/scheduler-firebase-sync.log'));

        // Opsional: cek inventaris storage bulanan untuk audit
        $schedule->command('storage:inventory --csv --path='.storage_path('app/private/inventory'))
            ->monthlyOn(1, '05:00')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/../Commands');

        require base_path('routes/console/console.php');
    }
}
