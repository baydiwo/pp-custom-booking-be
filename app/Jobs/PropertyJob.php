<?php

namespace App\Jobs;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\PropertyController;
use App\Models\Property;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PropertyJob implements ShouldQueue
{

    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    /**
     * Execute the job.
     *
     * @return void
     */
    public $tries = 3;
    public $timeout = 0;

    public function handle()
    {
        $model = new Property();
        $model->payload= "Gas". Carbon::now();
        $model->save();
        // Cache::flush();
        // $request = new Request();
        // $token = new ApiController(NULL, $request);
        // $dataToken = $token->authToken();
        // $api = new ApiController($dataToken['token'], $request);
        // $listProperty = $api->listProperty();

        // $dateInYear = $this->getDateInYear(date("Y")."-01-01", date("Y")."-12-31");
        // $chunck = array_chunk($dateInYear, 14);
        // $push = [];
        // for ($i=0; $i <= count($chunck[0]) ; $i++) { 
        //     for ($j=0; $j < $i; $j++) { 
        //         $push[$i][$j] = $chunck[0][$j];
        //     }
        // }

        // $push2 = [];
        // foreach ($push as $key => $value) {
        //     if($key != 1) {
        //         $push2[$key]['first']= reset($value);
        //         $push2[$key]['last']= end($value);
        //     }
        // }

        // $newArrayValue = array_values($push2);
        // if($listProperty) {
        //     foreach ($listProperty as $keyProp => $valueProp) {
        //         foreach ($newArrayValue as $keyNew => $valueNew) {
        //             $paramMinNight = [
        //                 'categoryIds' => [4],
        //                 // 'categoryIds' => [$this->params['categoryId']],
        //                 'dateFrom'    => $valueNew['first'],
        //                 'dateTo'      => $valueNew['last'],
        //                 'propertyId'  => $valueProp['id'],
        //                 // 'rateIds'     => [$this->params['rateIds']]
        //                 'rateIds'     => [1418]
        //             ];    
        //             $cacheName = [
        //                 'propertyId'=>$valueProp['id'],
        //                 'from'=>$valueNew['first'],
        //                 'to'=>$valueNew['last'],
        //             ];

        //             Cache::remember(json_encode($cacheName)
        //             , 10 * 60, function () use ($api, $paramMinNight) {
        //                 return $api->availabilityrategrid($paramMinNight);
        //             });
        //         }
        //     }
        // }

        return [
            'code' => 1,
            'status' => 'success',
            'data' => [],
            'message' => "Data Has Been Saved in Cache"
        ];
    }
    
    private function getDateInYear($first, $last, $step = '+1 day', $output_format = 'Y-m-d' )
    {
        $dates = array();
        $current = strtotime($first);
        $last = strtotime($last);
    
        while( $current <= $last ) {
            $dates[] = date($output_format , $current);
            $current = strtotime($step, $current);
        }
        return $dates;
    }

}
