<?php

namespace App\Console;

use App\Http\Controllers\ApiController;
use App\Jobs\PropertyConcurrentJob;
use App\Jobs\PropertyAvailabilityJob;
use App\Jobs\PropertyConcurrentJobFirst;
use App\Jobs\PropertyConcurrentJobSecond;
use App\Jobs\PropertyConcurrentJobThird;
use App\Jobs\PropertyAvailabilityDateJob;
use App\Jobs\PropertyConcurrentJobTest;
use App\Jobs\PropertyJob;
use App\Jobs\PropertyDetailsJob;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use phpDocumentor\Reflection\Types\Null_;
use Carbon\Carbon;

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
        $schedule->job(new PropertyAvailabilityDateJob(env('PROPERTY_ID')))->everyFifteenMinutes();
        $schedule->job(new PropertyDetailsJob())->dailyAt('00:00');
		
		//12AM schedule - Perth Time
		$schedule->job(new PropertyConcurrentJob(env('PROPERTY_ID')))->dailyAt('16:00');

		//6AM schedule - Perth Time
		$schedule->job(new PropertyConcurrentJob(env('PROPERTY_ID')))->dailyAt('22:00');

		//12PM schedule - Perth Time
		$schedule->job(new PropertyConcurrentJob(env('PROPERTY_ID')))->dailyAt('04:00');
		
		//6PM schedule - Perth Time
		$schedule->job(new PropertyConcurrentJob(env('PROPERTY_ID')))->dailyAt('10:00');
    }
}   
