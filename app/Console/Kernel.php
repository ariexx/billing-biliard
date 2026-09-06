<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Dijalankan oleh `php artisan schedule:work` yang di-start dari start.bat.
        // Mesin kasir Windows tidak punya cron, jadi jadwal hanya hidup selama
        // aplikasi hidup — karena itu jamnya dipilih di dalam jam operasional.
        $schedule->command('orders:expire-sessions')
            ->everyMinute()
            ->withoutOverlapping();

        foreach (config('backup.schedule_hours') as $hour) {
            $schedule->command('backup:database')
                ->dailyAt(sprintf('%02d:00', $hour))
                ->withoutOverlapping()
                ->runInBackground();
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
