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
        $dateInYear = $this->getDateInYear(date("Y") . "-01-01", date("Y") . "-12-31");
        $allGroupDate  = [];
        // foreach ($dateInYear as $valueDate) {
        //     if($valueDate != "2021-12-31") {
        //         $thisDay = Carbon::parse($valueDate);
        //         $groupDate = [];
        //         for ($i=1; $i <= 14; $i++) {
        //             $thisDay->addDays($i);
        //             array_push($groupDate, $thisDay);
        //             $thisDay = Carbon::parse($valueDate);
        //         }

        //         $allGroupDate[$valueDate] =  $groupDate;
        //     }
        // }

        $thisDay = "";
        foreach ($dateInYear as $valueDate) {
            if ($valueDate != "2021-12-31") {
                if ($valueDate == $thisDay || $thisDay == "") {

                    $prevDay = Carbon::parse($valueDate);
                    $thisDay = $prevDay->addDays(14)->format('Y-m-d');
                    $allGroupDate[$valueDate] = $thisDay;
                }
            }
        }


        // Cache::flush();
        //com
        $request       = new Request();
        $token         = new ApiController(NULL, $request);
        $dataToken     = $token->authToken();
        $api           = new ApiController($dataToken['token'], $request);
        $listAreasData = $api->listArea($this->propertyId);
        $listArea      = collect($listAreasData)->where('inactive', false)->all();
        $listRatesData = collect($api->listRates());

        $name = 'Night Direct';
        $filtered = $listRatesData->filter(function ($item) use ($name) {
            return false !== stripos($item['name'], $name);
        })->all();

        $listRates = collect($filtered)->pluck('id');

        foreach ($listArea as $keys => $listAreas) {
            foreach ($allGroupDate as $keyNew => $valueNew) {
                // $getRate = $this->rateByDate($keyNew, $valueIn, $listRates);
                $paramMinNight = [
                    'categoryIds' => [$listAreas['categoryId']],
                    'dateFrom'    => $keyNew,
                    'dateTo'      => $valueNew,
                    'propertyId'  => $listAreas['propertyId'],
                    'rateIds'     => $listRates
                ];

                $availGrid = $api->availabilityrategrid($paramMinNight);
                if (isset($availGrid['Message'])) {
                    if ($availGrid['Message'] == "Auth Token Has Expired") {
                        $api  = new ApiController($dataToken['token'], $request);
                        $availGrid = $api->availabilityrategrid($paramMinNight);
                    }
                }

                $model = new Property();
                $model->property_id = $listAreas['propertyId'];
                $model->area_id     = $listAreas['id'];
                $model->category_id = $listAreas['categoryId'];
                $model->date_from   = $keyNew;
                $model->date_to     = $valueNew;
                $model->response    = json_encode($availGrid);
                $model->state       = 1;
                $model->save();
            }
        }

        return [
            'code' => 1,
            'status' => 'success',
            'message' => "Data Has Been Saved in Cache"
        ];
    }

    private function getDateInYear($first, $last, $step = '+1 day', $output_format = 'Y-m-d')
    {
        $dates = array();
        $current = strtotime($first);
        $last = strtotime($last);

        while ($current <= $last) {
            $dates[] = date($output_format, $current);
            $current = strtotime($step, $current);
        }
        return $dates;
    }

    public function is_leap_year($year)
    {
        return ((($year % 4) == 0) && ((($year % 100) != 0) || (($year % 400) == 0)));
    }
    function printCombination(
        $arr,
        $n,
        $r
    ) {
        // A temporary array to 
        // store all combination 
        // one by one 
        $data = array();

        // Print all combination 
        // using temprary array 'data[]' 
        $this->combinationUtil(
            $arr,
            $data,
            0,
            $n - 1,
            0,
            $r
        );
    }

    /* arr[] ---> Input Array 
    data[] ---> Temporary array to 
                store current combination 
    start & end ---> Staring and Ending 
                    indexes in arr[] 
    index ---> Current index in data[] 
    r ---> Size of a combination 
        to be printed */
    function combinationUtil(
        $arr,
        $data,
        $start,
        $end,
        $index,
        $r
    ) {
        // Current combination is ready 
        // to be printed, print it 
        if ($index == $r) {
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
        for (
            $i = $start;
            $i <= $end &&
                $end - $i + 1 >= $r - $index;
            $i++
        ) {
            $data[$index] = $arr[$i];
            $this->combinationUtil(
                $arr,
                $data,
                $i + 1,
                $end,
                $index + 1,
                $r
            );
        }
    }

    function array_combinations($array)
    {

        $result = [];
        for ($i = 0; $i < count($array) - 1; $i++) {
            $result = array_merge($result, $this->combinations(array_slice($array, $i)));
        }

        return $result;
    }

    function combinations($array)
    {
        //get all the possible combinations no dublicates
        $combinations = [];
        $combinations[] = $array;
        for ($i = 1; $i < count($array); $i++) {

            $tmp = $array;
            unset($tmp[$i]);

            $tmp = array_values($tmp); //fix the indexes after unset

            if (count($tmp) < 2) {
                break;
            }
            $combinations[] = $tmp;
        }

        return $combinations;
    }

    public function rateByDate($dateFrom, $dateTo, $listRates)
    {
        $listRates = collect($listRates);
        $from = Carbon::parse($dateFrom);
        $diff = $from->diffInDays($dateTo);

        if ($diff == 1 || $diff == 2) {
            $rateId = $listRates->where('id', 12)->first();
        }

        if ($diff == 3) {
            $rateId = $listRates->where('id', 2)->first();
        }

        if ($diff == 4) {
            $rateId = $listRates->where('id', 3)->first();
        }

        if ($diff == 5) {
            $rateId = $listRates->where('id', 4)->first();
        }

        if ($diff == 6) {
            $rateId = $listRates->where('id', 5)->first();
        }

        if ($diff >= 7) {
            $rateId = $listRates->where('id', 6)->first();
        }

        return $rateId['id'];
    }
}
