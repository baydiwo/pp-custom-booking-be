<?php

namespace App\Console;

use App\Http\Controllers\ApiController;
use App\Jobs\PropertyConcurrentJob;
use App\Jobs\PropertyConcurrentJobFirst;
use App\Jobs\PropertyConcurrentJobSecond;
use App\Jobs\PropertyJob;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use phpDocumentor\Reflection\Types\Null_;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        //$schedule->job(new PropertyConcurrentJob(env('PROPERTY_ID')))->dailyAt('12:40');//daily();
		$schedule->job(new PropertyConcurrentJobFirst(env('PROPERTY_ID')))->dailyAt('05:37');
		$schedule->job(new PropertyConcurrentJobSecond(env('PROPERTY_ID')))->dailyAt('05:38');
    }
}   
