<?php

namespace App\Console;

use App\Http\Controllers\ApiController;
use App\Jobs\PropertyConcurrentJob;
use App\Jobs\PropertyAvailabilityJob;
use App\Jobs\PropertyConcurrentJobFirst;
use App\Jobs\PropertyConcurrentJobSecond;
use App\Jobs\PropertyConcurrentJobThird;
//use App\Jobs\PropertyConcurrentJobFourth;
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
        $schedule->job(new PropertyAvailabilityJob(env('PROPERTY_ID')))->everyTenMinutes();
        $schedule->job(new PropertyDetailsJob())->dailyAt('00:00');
		$schedule->job(new PropertyConcurrentJobFirst(env('PROPERTY_ID')))->dailyAt('04:00');
		$schedule->job(new PropertyConcurrentJobSecond(env('PROPERTY_ID')))->dailyAt('04:00');
		$schedule->job(new PropertyConcurrentJobThird(env('PROPERTY_ID')))->dailyAt('04:00');
		//$schedule->job(new PropertyConcurrentJobFourth(env('PROPERTY_ID')))->dailyAt('04:00');
    }
}   
