<?php

namespace App\Console;

use App\Http\Controllers\ApiController;
use App\Jobs\PropertyConcurrentJob;
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
        // $schedule->job(new PropertyJob(env('PROPERTY_ID')))->daily();
        $schedule->job(new PropertyConcurrentJob(env('PROPERTY_ID')))->daily();
        // $schedule->call(function () {
        //     echo "a";    
        // })->everyMinute();
    }
}   
