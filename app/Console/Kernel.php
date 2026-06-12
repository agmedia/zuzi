<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
        \App\Console\Commands\WoltTestZone::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('clean:authors')->dailyAt('00:03');
        $schedule->command('clean:publishers')->dailyAt('00:04');
        $schedule->command('sessions:prune-expired')->hourly()->withoutOverlapping();

        $schedule->command('sync:category-actions')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('check:wishlist')->everySixHours();//->everyMinute();
        $schedule->command('send:review-requests')->dailyAt('09:15')->withoutOverlapping();

        $reviewRequestBackfill = (array) config('settings.order.review_request.backfill', []);

        if ((bool) data_get($reviewRequestBackfill, 'enabled', false)) {
            $command = sprintf(
                'send:review-requests --min-days=%d --max-days=%d --limit=%d --sleep=%d%s',
                max(1, (int) data_get($reviewRequestBackfill, 'min_days', 500)),
                max(1, (int) data_get($reviewRequestBackfill, 'max_days', 600)),
                max(1, (int) data_get($reviewRequestBackfill, 'limit', 6)),
                max(0, (int) data_get($reviewRequestBackfill, 'sleep', 10)),
                (bool) data_get($reviewRequestBackfill, 'force_coupon', false) ? ' --force-coupon' : ''
            );

            $schedule->command($command)->everyMinute()->withoutOverlapping();
        }
        $schedule->command('sync:shipment-tracking --limit=50 --stale-minutes=15')->everyFifteenMinutes()->withoutOverlapping();
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
