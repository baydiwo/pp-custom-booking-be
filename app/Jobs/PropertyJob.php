<?php

namespace App\Jobs;

use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PropertyJob extends Job
{
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $model = new Property();
        $model->payload = "Gas".Carbon::now();
        $model->created_at = Carbon::now();
        $model->save();
    }
}
