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
    protected $propertyId;
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
    public function __construct($propertyId)
    {
        $this->propertyId  = $propertyId;
    }

    public function handle()
    {
        //todo
        //just 1 month, day by day
        Cache::flush();
        $request = new Request();
        $token = new ApiController(NULL, $request);
        $dataToken = $token->authToken();
        $api = new ApiController($dataToken['token'], $request);
        $listAreasData = $api->listArea($this->propertyId);
        $listRates = $api->listRates();
        $listArea = collect($listAreasData)->where('inactive', false)->all();
        $leap = $this->is_leap_year(date("Y"));
        $dateLeap = "28";
        if($leap){
            $dateLeap =  "29";
        } 

        $dateInYearJan = $this->getDateInYear(date("Y")."-01-01", date("Y")."-01-31");

        $dateInYearFeb = $this->getDateInYear(date("Y")."-02-01", date("Y")."-02-{$dateLeap}");
        $currentData = [];
        $currentIndex = 0;
        $temp = [];
        foreach ($dateInYearJan as $key => $value) {
            if($currentData){
                $temp[$key][] = $currentData;
                $temp[$key][] = $value;
                // $currentIndex = $key;
            }
            $currentData = $value;
        }

        // $chunck = array_chunk($dateInYearJan, 4);
        // $push = [];
        // for ($i=0; $i <= count($dateInYearJan) ; $i++) { 
        //     for ($j=0; $j < $i; $j++) { 
        //         $push[$i][$j] = $dateInYearJan[$j];
        //     }
        // }

        // $push2 = [];
        // foreach ($push as $key => $value) {
        //     if($key != 1) {
        //         $push2[$key][]= reset($value);
        //         $push2[$key][]= end($value);
        //     }
        // }
        $newArrayValue = array_values($temp);

        //testing array combination
        // $arrays =array(
        //     'item1' => array('A', 'B'),
        //     'item2' => array('C', 'D'),
        //     'item3' => array('E', 'F'),
        // );
        // $result = array(array());
        // foreach ($arrays as $property => $property_values) {
        //     $tmp = array();
        //     foreach ($result as $result_item) {
        //         foreach ($property_values as $property_value) {
        //             $tmp[] = array_merge($result_item, array($property => $property_value));
        //         }
        //     }
        //     $result = $tmp;
        // }
        if($listArea) {
            foreach ($listArea as $keyProp => $valueProp) {
                foreach ($newArrayValue as $keyNew => $valueNew) {
                    foreach ($listRates as $keyListRates => $valueListRates) {
                        $paramMinNight = [
                            // 'agentId'     => env("AGENT_ID"),
                            'categoryIds' => [$valueProp['categoryId']],
                            'dateFrom'    => $valueNew[0],
                            'dateTo'      => $valueNew[1],
                            'propertyId'  => $valueProp['propertyId'],
                            'rateIds'     => [$valueListRates['id']]
                        ];    
                        // $cacheName = [
                        //     'propertyId'=>$valueProp['id'],
                        //     'from'=>$valueNew['first'],
                        //     'to'=>$valueNew['last'],
                        // ];
                        Cache::remember("prop1_area".$keyProp
                        , 20 * 60, function () use ($api, $paramMinNight) {
                            return $api->availabilityrategrid($paramMinNight);
                        });
                    }
                    // Cache::remember("get_min_night_prop_{$valueProp['id']}".json_encode($cacheName)
                    // , 10 * 60, function () use ($api, $paramMinNight) {
                    //     return $api->availabilityrategrid($paramMinNight);
                    // });
                }
            }
        }

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

    public function is_leap_year($year)
    {
        return ((($year % 4) == 0) && ((($year % 100) != 0) || (($year %400) == 0)));
    }
    function printCombination($arr, 
                            $n, $r) 
    { 
        // A temporary array to 
        // store all combination 
        // one by one 
        $data = array(); 

        // Print all combination 
        // using temprary array 'data[]' 
        $this->combinationUtil($arr, $data, 0, 
                        $n - 1, 0, $r); 
    } 

    /* arr[] ---> Input Array 
    data[] ---> Temporary array to 
                store current combination 
    start & end ---> Staring and Ending 
                    indexes in arr[] 
    index ---> Current index in data[] 
    r ---> Size of a combination 
        to be printed */
    function combinationUtil($arr, $data, $start, 
                            $end, $index, $r) 
                    
    { 
        // Current combination is ready 
        // to be printed, print it 
        if ($index == $r) 
        { 
            for ($j = 0; $j < $r; $j++) 
                echo $data[$j]; 
            echo "\n"; 
            return; 
        } 

        // replace index with all 
        // possible elements. The 
        // condition "end-i+1 >= 
        // r-index" makes sure that 
        // including one element at 
        // index will make a combination 
        // with remaining elements at 
        // remaining positions 
        for ($i = $start; 
            $i <= $end && 
            $end - $i + 1 >= $r - $index; $i++) 
        { 
            $data[$index] = $arr[$i]; 
            $this->combinationUtil($arr, $data, $i + 1, 
                            $end, $index + 1, $r); 
        } 
    } 



}
