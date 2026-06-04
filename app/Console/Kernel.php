<?php

namespace App\Console;

use App\Console\Commands\PerformShiftChange;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    /** @var array<int, class-string> */
    protected $commands = [
        PerformShiftChange::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Shift digest emails: send at the end of each shift
        $schedule->command('feed:digest --shift=A')->dailyAt('14:00'); // Shift A ends 14:00
        $schedule->command('feed:digest --shift=B')->dailyAt('22:00'); // Shift B ends 22:00
        $schedule->command('feed:digest --shift=C')->dailyAt('06:00'); // Shift C ends 06:00
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
