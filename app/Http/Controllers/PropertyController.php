<?php

namespace App\Http\Controllers;

use App\Jobs\PropertyConcurrentJob;
use App\Jobs\PropertyJob;
use App\Models\ModelPropertyJob;
use App\Models\Property;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use phpDocumentor\Reflection\Types\This;

class PropertyController
{
    private $authToken;
    private $request;
    private $params;

    public function __construct(Request $request)
    {
        $this->authToken = Cache::get('authToken')['token'];
        $this->request = $request;
        $this->params  = $request->all();
    }

    public function list()
    {
        $api = new ApiController($this->authToken, $this->request);
        $data = $api->listProperty();
        return [
            'code' => 1,
            'status' => 'success',
            'data' => $data
        ];
    }
    public function detail($id)
    {
        $api = new ApiController($this->authToken, $this->request);
        $validator = Validator::make(
            $this->params,
            Property::$rules['detail']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));

        $detailProperty = $api->detailProperty($id);

        if ((count($detailProperty) == 0) || isset($detailProperty['Message'])) {
            throw new Exception(ucwords('Detail Property Not Found'));
        }

        $paramMinNight = [
            'categoryIds' => [$this->params['categoryId']],
            'dateFrom'    => $this->params['arrivalDate'],
            'dateTo'      => $this->params['departureDate'],
            'propertyId'  => $id,
            'rateIds'     => [$this->params['rateTypeId']]
        ];

        $minNight = $api->availabilityrategrid($paramMinNight);
        if (!$minNight) {
            throw new Exception(ucwords('Minimum Night Not Found'));
        } elseif (isset($minNight['Message'])) {
            throw new Exception(ucwords($minNight['Message']));
        }
        if (empty($minNight['categories'][0]['rates'])) {
            throw new Exception(ucwords('Rate Not Found'));
        }

        $detailSetting = $api->detailPropertySetting($id);
        if (isset($detailSetting['Message'])) {
            throw new Exception(ucwords($detailSetting['Message']));
        }

        $detailCategory = $api->detailCategory($id);
        if (isset($detailCategory['Message'])) {
            throw new Exception(ucwords($detailCategory['Message']));
        }

        if (($this->params['adults'] + $this->params['infants'] + $this->params['children']) > $detailCategory['maxOccupantsPerCategory']) {
            throw new Exception(ucwords('Occupants over limit'));
        }

        $areaConfiguration = $api->areaConfiguration($id);
        if (isset($areaConfiguration['Message'])) {
            throw new Exception(ucwords($areaConfiguration['Message']));
        }

        $paramsRateQuote = [
            'adults'        => $this->params['adults'],
            'areaId'        => $this->params['areaId'],
            'arrivalDate'   => $this->params['arrivalDate'],
            'categoryId'    => $this->params['categoryId'],
            'children'      => $this->params['children'],
            'departureDate' => $this->params['departureDate'],
            'infants'       => $this->params['infants'],
            'propertyId'    => $id,
            'rateTypeId'    => $this->params['rateTypeId'],
        ];

        $rateQuote = $api->rateQuote($paramsRateQuote);

        if (isset($rateQuote['Message'])) {
            throw new Exception(ucwords($rateQuote['Message']));
        }
        $to   = Carbon::createFromFormat('Y-m-d H:s:i', $this->params['arrivalDate']);
        $from = Carbon::createFromFormat('Y-m-d H:s:i', $this->params['departureDate']);
        $data['propertyId']      = $id;
        $data['propertyName']    = $detailProperty[0]['name'];
        $data['petAllowed']      = $detailSetting['petsAllowed'];
        $data['maxOccupants']    = $detailCategory['maxOccupantsPerCategory'];
        $data['totalGuests']     = $this->params['adults'] . ' adults, ' . $this->params['children'] . ' children, ' . $this->params['infants'] . ' infants';
        $data['totalRooms']      = $detailCategory['numberOfAreas'];
        $data['totalBedrooms']   = $areaConfiguration['numberOfBedrooms'];
        $data['totalBaths']      = $areaConfiguration['numberOfFullBaths'];
        $data['nights']          = $to->diffInDays($from);
        $data['accomodation']    = collect($rateQuote['rateBreakdown'])->sum('totalRate');
        $data['petFee']          = $detailSetting['petsAllowed'] == false ? 0 : 150;
        $data['totalAmount']     = $data['accomodation'] + $data['petFee'];
        $data['dueToday']        = $rateQuote['firstNightRate'];

        return [
            'code' => 1,
            'status' => 'success',
            'data' => $data
        ];
    }

    public function availabilityGrid()
    {
        $validator = Validator::make(
            $this->params,
            Property::$rules['availability-grid']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));

        // Cache::flush();
        // Queue::pushOn(
        // 'import-talent-queue',new PropertyJob()
        // );

        dispatch(new PropertyJob($this->params['propertyId']));
        return [
            'code' => 1,
            'status' => 'success',
            'data' => [],
            'message' => "Data Has Been Saved in Cache"
        ];
    }

    public function availabilityGridTestConcurrent()
    {
        $validator = Validator::make(
            $this->params,
            Property::$rules['availability-grid']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));

        dispatch(new PropertyConcurrentJob($this->params['propertyId']));
        return [
            'code' => 1,
            'status' => 'success',
            'data' => [],
            'message' => "Data Has Been Saved in Cache"
        ];
    }

    public function availabilityGridConcurrent()
    {
        $concurrent = 10;
        $validator = Validator::make(
            $this->params,
            Property::$rules['availability-grid']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
        $client = new Client([
            'http_errors'     => false,
            // 'connect_timeout' => 1.50, //////////////// 0.50
            // 'timeout'         => 2.00, //////////////// 1.00
            'headers' => [
                'User-Agent' => 'Test/1.0',
                'authToken' => $this->authToken,
            ],
            "content-type" => 'application/json'
        ]);
        $responses = collect();
        $endpoint = 'availabilityRateGrid';
        $requests = function ($total) use ($endpoint, $concurrent) {
            $uris = env('BASE_URL_RMS') . $endpoint;
            $paramMinNight = [
                'categoryIds' => [
                    3,
                    6,
                    7,
                    8,
                    9,
                    10,
                    11,
                    20,
                    23,
                    24,
                    25,
                    26,
                    27,
                    28,
                    29,
                    31,
                    32,
                    33,
                    34,
                    35,
                    36,
                    37,
                    41,
                    42,
                    43,
                    44,
                    45,
                    47,
                    48,
                    49,
                    50,
                    51,
                    52,
                    64,
                    65,
                    66,
                    68,
                    70,
                    103,
                    104,
                    105,
                    106,
                    107,
                    108,
                    109,
                    110,
                    111,
                    114,
                    115,
                    116,
                    117,
                    118,
                    119,
                    120,
                    122,
                    123,
                    124,
                    125,
                    126,
                    127,
                    128,
                    130,
                    131,
                    132,
                    133,
                    134,
                    136,
                    144,
                    145,
                    146,
                    147,
                    148,
                    149,
                    150,
                    156,
                    157,
                    158,
                    159,
                    160,
                    161,
                    162,
                    163,
                    164,
                    165,
                    166,
                    167,
                    168,
                    169,
                    170,
                    171,
                    173,
                    174,
                    175,
                    176,
                    177,
                    178,
                    179,
                    181,
                    183,
                    184,
                    185,
                    186,
                    187,
                    188,
                    189,
                    216,
                    217,
                    219,
                    220,
                    221,
                    222,
                    263,
                    264,
                    265,
                    266,
                    267,
                    268,
                    269,
                    270
                ],
                'dateFrom'    => "2021-01-01",
                'dateTo'      => "2021-01-15",
                'propertyId'  => 1,
                'rateIds'     => [2, 3, 4, 5, 6, 12]
            ];

            for ($i = 0; $i < $concurrent; $i++) {
                yield new Psr7Request('POST', $uris, [
                    'headers' => [
                        'authToken' => $this->authToken,
                    ],
                    "content-type" => 'application/json'
                ], json_encode($paramMinNight));
            }
        };
        // wait on all of the requests to complete. Throws a ConnectException if any
        $pool = new Pool($client, $requests($concurrent), [
            'concurrency' => $concurrent,
            'fulfilled' => function ($response, $index) use ($responses) {
                // $body = $response->getBody();

                $content = $response->getReasonPhrase();
                // $content = $body->getContents();
                $responses[$index] = $content;
            },
            'rejected' => function (ConnectException $reason, $index) use ($responses) {
                $body = $reason->getMessage();

                $responses[$index] = $body;
            },
        ]);
        // Initiate the transfers and create a promise
        $promise = $pool->promise();
        // Force the pool of requests to complete.
        $promise->wait();

        // foreach ($promise as $key => $value) {
        // }
        return [
            'code' => 1,
            'status' => 'success',
            'data' => [],
            'message' => "Data Has Been Saved in Cache"
        ];
    }

    public function rateByDate($dateFrom, $dateTo)
    {
        $diff = $dateFrom->diffInDays($dateTo);

        if ($diff == 1 || $diff == 2) {
            $rateId = 12;
        }

        if ($diff == 3) {
            $rateId = 2;
        }

        if ($diff == 4) {
            $rateId = 3;
        }

        if ($diff == 5) {
            $rateId = 4;
        }

        if ($diff == 6) {
            $rateId = 5;
        }

        if ($diff >= 7) {
            $rateId = 6;
        }

        return $rateId;
    }

    public function checkAvailability()
    {

        $validator = Validator::make(
            $this->params,
            Property::$rules['check-availability']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));

        $from = Carbon::parse($this->params['dateFrom']);
        $to = Carbon::parse($this->params['dateTo']);
        $diff = $from->diffInDays($to);
        if ($diff > 14) {
            throw new Exception("Different Days Cannot Greater Than 14 Days");
        }

        $getRate = $this->rateByDate($from, $to);
        $result = Property::select('response')
            ->where('property_id', $this->params['propertyId'])
            ->where('area_id', $this->params['areaId'])
            // ->whereBetween('date_from', [$from, $to])
            ->where('date_from', '<=', $from)
            ->orderBy('date_from', 'DESC')
            ->first();
        $new = json_decode($result->response);

        $collect = collect($new->categories[0]->rates)->where('rateId', $getRate)->values()->first();
        $dayBreakDown2 = collect();
        $dayBreakDown = collect();
        if($collect) {
            $dayBreakDown = collect($collect->dayBreakdown)
                ->whereBetween('theDate', [$this->params['dateFrom'], $this->params['dateTo']]);

            if($dayBreakDown){
                //check another date to
                $checkAnotherDate = $dayBreakDown->where('theDate', $to)->all();
                if(!$checkAnotherDate) {
                    $result2 = Property::select('response')
                    ->where('property_id', $this->params['propertyId'])
                    ->where('area_id', $this->params['areaId'])
                    // ->whereBetween('date_from', [$from, $to])
                    ->where('date_from', '<=', $to)
                    ->orderBy('date_from', 'DESC')
                    ->first();        
                }
                $new2 = json_decode($result2->response);

                $collect2 = collect($new2->categories[0]->rates)->where('rateId', $getRate)->values()->first();
                $dayBreakDown2 = collect($collect2->dayBreakdown)
                        ->where('theDate', '<=', $this->params['dateTo']);
        
            }
            $merge = $dayBreakDown->merge($dayBreakDown2)->all();
            $collect->dayBreakdown = $merge;
        }
        // $name = "prop1_area_".$this->params['areaId']."_from_".$this->params['dateFrom'].
        // "_to_". $this->params['dateTo'];

        // $redis = Cache::getRedis();
        // $keys = $redis->keys("*{$name}*");
        // // $count = 0;
        // $result = [];

        // foreach ($keys as $key) {
        //     $result[] = $red

        // }      

        // $newResult  = [];
        // foreach ($result as $value) {
        //     $newResult[] = unserialize($value);
        // }

        return [
            'code' => $collect == NULL ? 0 : 1,
            'status' => 'success',
            'data' => [
                "categories" => [
                    "categoryId" => $new->categories[0]->categoryId,
                    "name" => $new->categories[0]->name,
                    "rates" => $collect == NULL ? [] : $collect,
                ]
            ]
        ];


        return $result;

        // $api = new ApiController($this->authToken, $this->request);
        // $listProperty = $api->listProperty();

        // $dateInYear = $this->getDateInYear(date("Y")."-01-01", date("Y")."-12-31");
        // $chunck = array_chunk($dateInYear, 14);
        // $push = [];
        // $temp = "";
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
        //                 'categoryIds' => [$this->params['categoryId']],
        //                 'dateFrom'    => $valueNew['first'],
        //                 'dateTo'      => $valueNew['last'],
        //                 'propertyId'  => $valueProp['id'],
        //                 'rateIds'     => [$this->params['rateIds']]
        //             ];    

        //             Cache::remember('min_night_prop'.$valueProp['id']."from {$valueNew['first']} - to {$valueNew['last']}"
        //             , 10 * 60, function () use ($api, $paramMinNight) {
        //                 return $api->availabilityrategrid($paramMinNight);
        //             });
        //         }
        //     }
        // }

        // return "Data Has Been Saved in Cache";
    }

    public function checkAvailabilityConcurrent()
    {

        $validator = Validator::make(
            $this->params,
            Property::$rules['check-availability']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));

        $from = Carbon::parse($this->params['dateFrom']);
        $to = Carbon::parse($this->params['dateTo']);
        $fromYear = $from->year;
        $fromTo = $to->year;
        // $diff = $from->diffInDays($to);
        // if ($diff > 14) {
        //     throw new Exception("Different Days Cannot Greater Than 14 Days");
        // }
        if ($fromYear != date('Y')) {
            throw new Exception("Date from  Cannot Greater From This Year");
        }

        if ($fromTo != date('Y')) {
            throw new Exception("Date to  Cannot Greater From This Year");
        }

        $getRate = $this->rateByDate($from, $to);
        $result = Property::select('response')
            ->where('property_id', $this->params['propertyId'])
            ->where('area_id', $this->params['areaId'])
            // ->whereBetween('date_from', [$from, $to])
            ->where('date_from', '<=', $from)
            ->orderBy('date_from', 'DESC')
            ->first();

        $new = json_decode($result->response);
        $collect = collect($new->categories[0]->rates)->where('rateId', $getRate)->values()->first();
        $dayBreakDown2 = collect();
        $dayBreakDown = collect();
        $rest = [];

        if($collect) {
            $collBreakDown = collect($collect->dayBreakdown);

            //check date from
            $dayFrom = $collBreakDown->where('theDate', $from);

            $dayBreakDown = $collBreakDown->whereBetween('theDate', [$this->params['dateFrom'], $this->params['dateTo']]);
            if(array_key_exists(1, $dayFrom->all()) == FALSE)
            {
                $dayBreakDown = $dayBreakDown->values()->all();

                $dayBreakDown[0]->dailyRate = $collBreakDown->first()->dailyRate;

                $dayBreakDown = collect($dayBreakDown);
            }

        if($dayBreakDown){
                //check another date to
                //kurangi satu hari
                $dateMin1 = Carbon::parse($to)->subDays(1);

                $checkAnotherDate = $dayBreakDown->where('theDate', $dateMin1)->all();

                if(!$checkAnotherDate) {
                    $result2 = Property::select('response')
                    ->where('property_id', $this->params['propertyId'])
                    ->where('area_id', $this->params['areaId'])
                    // ->whereBetween('date_from', [$from, $to])
                    ->where('date_from', '<=', $to)
                    ->where('date_to', '>', $from)
                    ->groupBy('date_from')
                    ->orderBy('date_from', 'ASC')
                    ->get();   


                    $result2 = collect($result2)->values()->all();
                    array_shift($result2);
                    foreach ($result2 as $key2 => $value2) {
                        $new2 = json_decode($value2->response);
        
                        $collect2 = collect($new2->categories[0]->rates)->where('rateId', $getRate)->values()->first();
    
                        $dayBreakDown2 = collect($collect2->dayBreakdown)
                                ->where('theDate', '<=', $this->params['dateTo']);

                        if(count($dayBreakDown) > 1) {
                            $dayBreakDown2[0]->dailyRate = $dayBreakDown->last()->dailyRate;
                        } else {
                            if($dayBreakDown2->last()->dailyRate) {
                                $dayBreakDown2[0]->dailyRate = $dayBreakDown2->last()->dailyRate;
                            }
                        }

                        array_push($rest,$dayBreakDown2 );
                    }

                }

            }
            $merge = $dayBreakDown->merge(collect($rest)->flatten())->all();
            $collect->dayBreakdown = $merge;

        }
        return [
            'code' => $collect == NULL ? 0 : 1,
            'status' => 'success',
            'data' => [
                "categories" => [
                    "categoryId" => $new->categories[0]->categoryId,
                    "name" => $new->categories[0]->name,
                    "rates" => $collect == NULL ? [] : $collect,
                ]
            ]
        ];


        return $result;

    }

    public function checkAvailabilityConcurrentOld()
    {
        $api = new ApiController($this->authToken, $this->request);

        $validator = Validator::make(
            $this->params,
            Property::$rules['check-availability']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));

        $from = Carbon::parse($this->params['dateFrom']);
        $to = Carbon::parse($this->params['dateTo']);
        $diff = $from->diffInDays($to);
        if ($diff > 14) {
            throw new Exception("Different Days Cannot Greater Than 14 Days");
        }

        $allGroupDate  = [];
        $dateInYear = $this->getDateInYear(date("Y") . "-01-01", date("Y") . "-12-31");

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

        $area = $api->detailArea($this->params['areaId']);
        if(!$area) {
            throw new Exception("Area Not Found");
        }

        $getRate = $this->rateByDate($from, $to);
        $result = ModelPropertyJob::select('response')
            // ->where('response', 'LIKE', '%'. $from. '%')
            ->where('response', 'LIKE', '%name":Lantana Studio - Albany%' )
            ->get();
        die(json_encode($result));
        $new = json_decode($result->response);

        $collect = collect($new->categories[0]->rates)->where('rateId', $getRate)->values()->first();
        $dayBreakDown2 = collect();
        $dayBreakDown = collect();
        if($collect) {
            $dayBreakDown = collect($collect->dayBreakdown)
                ->whereBetween('theDate', [$this->params['dateFrom'], $this->params['dateTo']]);

            if($dayBreakDown){
                //check another date to
                $checkAnotherDate = $dayBreakDown->where('theDate', $to)->all();
                if(!$checkAnotherDate) {
                    $result2 = Property::select('response')
                    ->where('property_id', $this->params['propertyId'])
                    ->where('area_id', $this->params['areaId'])
                    // ->whereBetween('date_from', [$from, $to])
                    ->where('date_from', '<=', $to)
                    ->orderBy('date_from', 'DESC')
                    ->first();        
                }
                $new2 = json_decode($result2->response);

                $collect2 = collect($new2->categories[0]->rates)->where('rateId', $getRate)->values()->first();
                $dayBreakDown2 = collect($collect2->dayBreakdown)
                        ->where('theDate', '<=', $this->params['dateTo']);
        
            }
            $merge = $dayBreakDown->merge($dayBreakDown2)->all();
            $collect->dayBreakdown = $merge;
        }
        // $name = "prop1_area_".$this->params['areaId']."_from_".$this->params['dateFrom'].
        // "_to_". $this->params['dateTo'];

        // $redis = Cache::getRedis();
        // $keys = $redis->keys("*{$name}*");
        // // $count = 0;
        // $result = [];

        // foreach ($keys as $key) {
        //     $result[] = $red

        // }      

        // $newResult  = [];
        // foreach ($result as $value) {
        //     $newResult[] = unserialize($value);
        // }

        return [
            'code' => 1,
            'status' => 'success',
            'data' => [
                "categories" => [
                    "categoryId" => $new->categories[0]->categoryId,
                    "name" => $new->categories[0]->name,
                    "rates" => $collect == NULL ? [] : $collect,
                ]
            ]
        ];


        return $result;

        // $api = new ApiController($this->authToken, $this->request);
        // $listProperty = $api->listProperty();

        // $dateInYear = $this->getDateInYear(date("Y")."-01-01", date("Y")."-12-31");
        // $chunck = array_chunk($dateInYear, 14);
        // $push = [];
        // $temp = "";
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
        //                 'categoryIds' => [$this->params['categoryId']],
        //                 'dateFrom'    => $valueNew['first'],
        //                 'dateTo'      => $valueNew['last'],
        //                 'propertyId'  => $valueProp['id'],
        //                 'rateIds'     => [$this->params['rateIds']]
        //             ];    

        //             Cache::remember('min_night_prop'.$valueProp['id']."from {$valueNew['first']} - to {$valueNew['last']}"
        //             , 10 * 60, function () use ($api, $paramMinNight) {
        //                 return $api->availabilityrategrid($paramMinNight);
        //             });
        //         }
        //     }
        // }

        // return "Data Has Been Saved in Cache";
    }

    public function getDateInYear($first, $last, $step = '+1 day', $output_format = 'Y-m-d')
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

    public function areaByYear()
    {
        $validator = Validator::make(
            $this->params,
            Property::$rules['area-by-year']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));

        $data = Property::select('response')->where("area_id", $this->params['areaId'])->get();
        if (count($data) == 0) {
            throw new Exception(ucwords('Data Not Found'));
        }
        $newResponse = collect($data)->map(function ($data) {
            return json_decode($data->response);
        })->pluck('categories')->all();

        $response = [];
        foreach ($newResponse as $key => $value) {
            $valueCollect = collect($value[0]->rates)->pluck('dayBreakdown')->last();
            foreach ($valueCollect as $keys => $valueDataTemp) {
                $date = Carbon::parse($valueDataTemp->theDate)->format('Y-m-d');
                $response[$keys][$date] = $valueDataTemp;
            }
        }

        $temp = [];
        foreach ($response as $returnkey => $valuereturn) {
            foreach ($valuereturn as $keyreturn => $valueDataReturn) {
                $temp[$keyreturn] = $valueDataReturn;
            }
        }
        $dateReturn = collect($temp)->sortBy("theDate", SORT_NATURAL)->values()->all();

        return [
            'code' => 1,
            'status' => 'success',
            'data' => $dateReturn
        ];
    }
}
