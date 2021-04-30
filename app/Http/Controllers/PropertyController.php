<?php

namespace App\Http\Controllers;

use App\Jobs\PropertyConcurrentJob;
use App\Jobs\PropertyConcurrentJobFirst;
use App\Jobs\PropertyConcurrentJobSecond;
use App\Jobs\PropertyConcurrentJobThird;
use App\Jobs\PropertyConcurrentJobFourth;
use App\Jobs\PropertyAvailabilityJob;
use App\Jobs\PropertyJob;
use App\Jobs\PropertyDetailsJob;
use App\Models\ModelPropertyJob;
use App\Models\Property;
use App\Models\ModelPropertyAvailability;
use App\Models\PropertyDetails;
use App\Models\PropertyAreaDetails;
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
    public function detail_old($id)
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

    public function availabilityGridTestConcurrentOld()
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

        $nonFeePackageArea = [221, 124, 66, 67, 68, 70];
        $feePackage = 66;
        $validator = Validator::make(
            $this->params,
            Property::$rules['check-availability']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));

        $from = Carbon::parse($this->params['dateFrom']);
        $now = Carbon::now();
        $to = Carbon::parse($this->params['dateTo']);
        $nowYear = $now->modify('next year');
        if ($to > $nowYear) {
            throw new Exception("Date from Cannot Greater From One Year");
        }
        $diff = $from->diffInDays($to);
        // if ($diff > 14) {
        //     throw new Exception("Different Days Cannot Greater Than 14 Days");
        // }
        // if ($fromYear != date('Y')) {
        //     throw new Exception("Date from  Cannot Greater From This Year");
        // }

        // if ($fromTo != date('Y')) {
        //     throw new Exception("Date to  Cannot Greater From This Year");
        // }

        $getRate = $this->rateByDate($from, $to);

        $result = ModelPropertyJob::select('response')
            ->where('property_id', $this->params['propertyId'])
            // ->whereBetween('date_from', [$from, $to])
            ->where('date_from', '=', $from)
            // ->where('date_from', '<=', $to)
            ->first();
        $api           = new ApiController($this->authToken, $this->request);
        $detailArea = $api->detailArea($this->params['areaId']);

        $new = json_decode($result->response);
        $tempRate = [];
        foreach ($new as $keynew => $valuenew) {
            $json = json_decode($valuenew, true);
            $dataRate = collect($json['categories'])->where('categoryId', $detailArea['categoryId'])->values()->first();
            array_push($tempRate, $dataRate);
        }

            foreach ($tempRate as $valuetempRate) {
                foreach ($valuetempRate['rates'] as $valueDatatempRate) {
                    $countBreakDown = count($valueDatatempRate['dayBreakdown']);
                    $getRate = $this->rateByDate($from, $to);
                        if($diff <= 7) {
                            if($diff == $countBreakDown) {
                                if($getRate == $valueDatatempRate['rateId']) { 
                                    return [
                                        'code' => 1,
                                        'status' => 'success',
                                        'data' => [
                                            "categories" => [
                                                "categoryId" => $valuetempRate['categoryId'],
                                                "name" => $valuetempRate['name'],
                                                "rates" => $valueDatatempRate
                                            ]
                                        ]
                                    ];
                                } 
                            }							
                        } else {
                            $return = [];
                            if($valueDatatempRate['rateId'] == 6) {
                                $dateInYear = $this->getDateInYear($from, $to);
                                $getLast = collect($valueDatatempRate['dayBreakdown'])->last();
                                foreach ($dateInYear as $keydateInYear => $valuedateInYear) {
                                        $dateValueRate = Carbon::parse($valuedateInYear);
                                        $dateValueRateNow = Carbon::parse($getLast['theDate']);
                                        if($dateValueRate->gt($dateValueRateNow)){
                                            if($dateValueRate != $to) {

                                                $parse = [
                                                    "availableAreas" => $getLast['availableAreas'],
                                                    "closedOnArrival" => $getLast['closedOnArrival'],
                                                    "closedOnDeparture" => $getLast['closedOnDeparture'],
                                                    "dailyRate" => $getLast['dailyRate'],
                                                    "theDate" => $dateValueRate->format('Y-m-d H:i:s'),
                                                    "minStay" => $getLast['minStay'],
                                                    "minStayOnArrival" => $getLast['minStayOnArrival'],
                                                    "stopSell" => false,
                                                ];
    
                                                array_push($valueDatatempRate['dayBreakdown'], $parse);
                                            }
                                        }
                                    }

                                return [
                                    'code' => 1,
                                    'status' => 'success',
                                    'data' => [
                                        "categories" => [
                                            "categoryId" => $valuetempRate['categoryId'],
                                            "name" => $valuetempRate['name'],
                                            "rates" => $valueDatatempRate
                                        ]
                                    ]
                                ];

                            }
                    }


                }
            }
    }
	
    public function checkAvailabilityConcurrentNew($areaID = 0, $propertyID = 0, $dateFrom = '', $dateTo = '')
    {
        $nonFeePackageArea = [221, 124, 66, 67, 68, 70];

        $feePackage = 66;
		if($areaID > 0)
			$this->params['areaId'] = $areaID;
		if($propertyID > 0)
			$this->params['propertyId'] = $propertyID;
		if($dateFrom != '')
			$this->params['dateFrom'] = $dateFrom;
		if($dateTo != '')
			$this->params['dateTo'] = $dateTo;

        $validator = Validator::make(
            $this->params,
            Property::$rules['check-availability']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));

        $from = Carbon::parse($this->params['dateFrom']);
        $now = Carbon::now();
        $to = Carbon::parse($this->params['dateTo']);
        $nowYear = $now->modify('next year');
        if ($to > $nowYear) {
            throw new Exception("Date from Cannot Greater From One Year");
        }
		
        $diff = $from->diffInDays($to);
        $getRate = $this->rateByDate($from, $to);

        $result = ModelPropertyJob::select('response')
            ->where('property_id', $this->params['propertyId'])
            ->where('date_from', '=', $from)
            ->first();
        $api           = new ApiController($this->authToken, $this->request);
        $detailArea = $api->detailArea($this->params['areaId']);

        $new = json_decode($result->response);
        $tempRate = [];
		$tempVal = [];
		$resultData = [];
		foreach ($new as $keynew => $valuenew) {
			$json = json_decode($valuenew, true);
			$dataRate = collect($json['categories'])->where('categoryId', $detailArea['categoryId'])->values()->first();
			if(is_countable($dataRate) && count($dataRate) > 0)
				array_push($tempRate, $dataRate);
		}
		
		if(is_countable($tempRate) && count($tempRate) > 0)
		{
			foreach ($tempRate as $valuetempRate) {
				foreach ($valuetempRate['rates'] as $valueDatatempRate) {
					$countBreakDown = count($valueDatatempRate['dayBreakdown']);
					$getRate = $this->rateByDate($from, $to);
					if($diff > 7)
					{
						$diffDate = 7;
						if($diffDate == $countBreakDown) {
							if($getRate == $valueDatatempRate['rateId']) { 
								$tempVal = [
									'code' => 1,
									'status' => 'success',
									'data' => [
										"categories" => [
											"categoryId" => $valuetempRate['categoryId'],
											"name" => $valuetempRate['name'],
											"rates" => $valueDatatempRate
										]
									]
								];
							}
						}
					}
					else if($diff <= 7) {								
						$diffDate = $diff;
						if($diff == $countBreakDown) {
							if($getRate == $valueDatatempRate['rateId']) { 
								//if($valueDatatempRate['dayBreakdown'][0]['minStay'] <= ($getRate+1))
								//{
									$resultData = [
										'code' => 1,
										'status' => 'success',
										'data' => [
											"categories" => [
												"categoryId" => $valuetempRate['categoryId'],
												"name" => $valuetempRate['name'],
												"rates" => $valueDatatempRate
											]
										]
									];
									goto result;
								//}
							}
						}							
					} else {							
						$diffDate = $diff;
						$return = [];
						if($valueDatatempRate['rateId'] == 6) {
							$dateInYear = $this->getDateInYear($from, $to);
							$getLast = collect($valueDatatempRate['dayBreakdown'])->last();
							
							foreach ($dateInYear as $keydateInYear => $valuedateInYear) {
								$dateValueRate = Carbon::parse($valuedateInYear);
								$dateValueRateNow = Carbon::parse($getLast['theDate']);
								if($dateValueRate->gt($dateValueRateNow)){
									if($dateValueRate != $to) {
	
										$parse = [
											"availableAreas" => $getLast['availableAreas'],
											"closedOnArrival" => $getLast['closedOnArrival'],
											"closedOnDeparture" => $getLast['closedOnDeparture'],
											"dailyRate" => $getLast['dailyRate'],
											"theDate" => $dateValueRate->format('Y-m-d H:i:s'),
											"minStay" => $getLast['minStay'],
											"minStayOnArrival" => $getLast['minStayOnArrival'],
											"stopSell" => false,
										];
										
										array_push($valueDatatempRate['dayBreakdown'], $parse);
									}
								}
							}
	
							$resultData = [
								'code' => 1,
								'status' => 'success',
								'data' => [
									"categories" => [
										"categoryId" => $valuetempRate['categoryId'],
										"name" => $valuetempRate['name'],
										"rates" => $valueDatatempRate
									]
								]
							];
	
						}
					}
				}
			}
			
			if($diff > 7 && isset($tempVal['data']['categories']['rates']['dayBreakdown']))
			{
				$lday=count($tempVal['data']['categories']['rates']['dayBreakdown']);
				if(isset($tempVal['data']['categories']['rates']['dayBreakdown'][$lday-1]['theDate']))
				{
					$endDate = Carbon::parse($tempVal['data']['categories']['rates']['dayBreakdown'][$lday-1]['theDate']);
					if($endDate < $to)
					{
						$tempData = $this->fetchDataRecursive($this->params['propertyId'], $endDate, $to, $detailArea['categoryId'], $getRate);
						if(is_countable($tempData) && count($tempData) > 0)
						{
							foreach($tempData as $key => $tVal)
							{
								if($key > 0 && $tVal['theDate'] < $to)
									$tempVal['data']['categories']['rates']['dayBreakdown'][] = $tVal;
							}
						}
					}
				}
				$resultData = $tempVal;
			}
		}

		result:
		if(is_countable($resultData) && count($resultData) > 0)
		{
			if(isset($resultData['data']) && $resultData['data']['categories']['rates']['dayBreakdown'][0]['minStay'] <= ($resultData['data']['categories']['rates']['rateId']+1)){
				if($areaID > 0)
					return $resultData['data']['categories']['rates']['dayBreakdown'];
				else
					return $resultData;
			}
			else
				throw new Exception("Minimum stay allowed is ".$resultData['data']['categories']['rates']['dayBreakdown'][0]['minStay']." Nights");
		}
		else
		{
			$endDate = $to;
			$endDate = Carbon::parse($endDate)->format('Y-m-d');
			$enddate = Carbon::createFromFormat('Y-m-d', $endDate)->addDays(1)->format('Y-m-d');
			$diff = $from->diffInDays($enddate);
        	$getRate = $this->rateByDate($from, $enddate);
			$tempData = $this->fetchDataRecursive($this->params['propertyId'], $from, $enddate, $detailArea['categoryId'], $getRate);
			if(count($tempData) > 0)
			{
				if(isset($tempData[0]['rates'][0]['dayBreakdown'][0]['minStay']))
					$minStay = $tempData[0]['rates'][0]['dayBreakdown'][0]['minStay'];
				else
					$minStay = $tempData[0]['minStay'];
				throw new Exception("Minimum stay allowed is ".$minStay." Nights");
			}
			else
				throw new Exception("Data not available for selected date");
		}
    }
	
	private function fetchDataRecursive($propertyId, $from, $to, $categoryId, $getRate)
	{
		$result = ModelPropertyJob::select('response')
            ->where('property_id', $propertyId)
            ->where('date_from', '=', $from)
            ->first();

        $new = json_decode($result->response);
        $tempRate = $tempData = [];
        foreach ($new as $keynew => $valuenew) {
            $json = json_decode($valuenew, true);
            $dataRate = collect($json['categories'])->where('categoryId', $categoryId)->values()->first();
            array_push($tempRate, $dataRate);
        }

		foreach ($tempRate as $valuetempRate) {
			foreach ($valuetempRate['rates'] as $valueDatatempRate) {
				if($getRate == $valueDatatempRate['rateId'] && count($valueDatatempRate['dayBreakdown']) == $getRate) {
					$tempRate = $valueDatatempRate['dayBreakdown'];
				}
			}
		}
		if(count($tempRate) > 0)
		{
			$lday=count($tempRate);
			if(isset($tempRate[$lday-1]['theDate']))
			{
				$endDate = Carbon::parse($tempRate[$lday-1]['theDate']);
				if($endDate < $to)
				{
					$tempData = $this->fetchDataRecursive($propertyId, $endDate, $to, $categoryId, $getRate);
				}
			}
		}
		array_shift($tempData);
		return array_merge($tempRate,$tempData);
	}

    public function lowerWeek($collect, $to, $from, $getRate, $feePackage){

        $dayBreakDown2 = collect();
        $dayBreakDown = collect();

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
                $dateMin1 = Carbon::parse($to)->subDays(1);

                $checkAnotherDate = $dayBreakDown->where('theDate', $dateMin1)->all();

                if(!$checkAnotherDate) {
                    $result2 = Property::select('response')
                    ->where('property_id', $this->params['propertyId'])
                    ->where('area_id', $this->params['areaId'])
                    // ->whereBetween('date_from', [$from, $to])
                    ->where('date_from', '<=', $to)
                    ->orderBy('date_from', 'DESC')
                    ->first();   

                    $new2 = json_decode($result2->response);
                    $collect2 = collect($new2->categories[0]->rates)->where('rateId', $getRate)->values()->first();

                    $dayBreakDown2 = collect($collect2->dayBreakdown)
                            ->where('theDate', '<=', $this->params['dateTo']);

                    if(count($dayBreakDown2) > 0){
                        if(count($dayBreakDown) > 1) {
                            $last = $dayBreakDown2->last()->dailyRate;
                            $dayBreakDown2->map(function($value) use ($last){
                                $value->dailyRate = $last;
                                return $value;
                            });
                        }
                    }

                }
            }

            $merge = $dayBreakDown->merge($dayBreakDown2)->all();
            if(count($dayBreakDown2) > 0){
                $merge[0]->dailyRate = $dayBreakDown2->last()->dailyRate + $feePackage;
            }
            $collect->dayBreakdown = $merge;

            return $collect;    

        }

    }

    public function greaterWeek($collect, $to, $from, $getRate, $feePackage){

        $dayBreakDown2 = collect();
        $dayBreakDown = collect();

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

            $rest = [];
        if($dayBreakDown){
                //check another date to
                $dateMin1 = Carbon::parse($to)->subDays(1);

                $checkAnotherDate = $dayBreakDown->where('theDate', $dateMin1)->all();
                if(!$checkAnotherDate) {
                    $result2 = Property::select('response')
                    ->where('property_id', $this->params['propertyId'])
                    ->where('area_id', $this->params['areaId'])
                    // ->whereBetween('date_from', [$from, $to])
                    ->where('date_to', '>=', $from)
                    ->where('date_to', '<=', $to)
                    ->orderBy('date_from', 'ASC')
                    ->groupBy('date_from')
                    ->get();   
                    if($result2) {
                        foreach ($result2 as $keyresult2 => $valueresult2) {
                            $new2 = json_decode($valueresult2->response);

                            $collect2 = collect($new2->categories[0]->rates)->where('rateId', $getRate)->values()->first();

                            if($collect2){
                                $checkFirstCollect = collect($collect2->dayBreakdown)->where('theDate', '<', $from)->values()->all();

                                $checkFirst = collect($collect2->dayBreakdown)->forget(array_keys($checkFirstCollect));

                                if(count($checkFirst) == 0) {
                                    $dayBreakDown2 = collect($collect2->dayBreakdown)
                                        ->where('theDate', '<=', $this->params['dateTo'])->values();
                                } else {
                                    $except = collect($checkFirst)->where('theDate', $from);
                                    if(count($except) == 0){
                                        $dayBreakDown2 = collect($checkFirst)
                                            ->where('theDate', '<=', $this->params['dateTo'])->values();
                                    }
                                }
                                array_push($rest, $dayBreakDown2);
                            }
                        }
                    }
                }
            }

            $flatten = collect($rest)->flatten();
            $merge = $dayBreakDown->merge($flatten)->all();
            $return = collect($merge)->map(function($value, $key) use ($dayBreakDown2, $feePackage) {
                if(count($dayBreakDown2) > 0) {
                    if($key == 0) {
                        $value->dailyRate =$dayBreakDown2->last()->dailyRate + $feePackage;
                    } else {
                        $value->dailyRate =$dayBreakDown2->last()->dailyRate;
                    }

                    return $value;
                }
            });
            $collect->dayBreakdown = $return;

            return $collect;    

        }

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
	
	public function getAvailabilityDates()
	{
		$this->params['propertyId'] = 1;
        $validator = Validator::make(
            $this->params,
            ModelPropertyAvailability::$rules['check-availability-area']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
			
		$data = ModelPropertyAvailability::select('response')->where("area_id", $this->params['areaId'])->where("property_id", $this->params['propertyId'])->first();
        if (is_countable($data) && count($data) == 0) {
            throw new Exception(ucwords('Data Not Found'));
        }
		$result = json_decode($data->response);
		if(is_countable($result) && count($result) > 0)
		{
			return $result[0];
		}
		else
			throw new Exception(ucwords('Data Not Found'));
	}

    public function availabilityGridTestConcurrent()
    {
        $validator = Validator::make(
            $this->params,
            Property::$rules['availability-grid']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
			
		if(isset($this->params['jobId']) && $this->params['jobId'] == 1)
			dispatch(new PropertyConcurrentJobFirst($this->params['propertyId']));
		else if(isset($this->params['jobId']) && $this->params['jobId'] == 2)
			dispatch(new PropertyConcurrentJobSecond($this->params['propertyId']));
		else if(isset($this->params['jobId']) && $this->params['jobId'] == 3)
			dispatch(new PropertyConcurrentJobThird($this->params['propertyId']));
		else if(isset($this->params['jobId']) && $this->params['jobId'] == 0)
			dispatch(new PropertyAvailabilityJob(env('propertyId')));
		else
			dispatch(new PropertyConcurrentJob($this->params['propertyId']));
			
        return [
            'code' => 1,
            'status' => 'success',
            'data' => [],
            'message' => "Data Has Been Saved in Cache"
        ];
    }
	
	public function detail($id)
	{
		$from = Carbon::parse($this->params['arrivalDate'])->format('Y-m-d');
		$to = Carbon::parse($this->params['departureDate'])->format('Y-m-d');
		$priceData = $this->checkAvailabilityConcurrentNew($this->params['areaId'], $id, $from, $to);

		$price = 0;
		foreach($priceData as $data)
		{
			$price+=$data['dailyRate'];
		}
		$check = PropertyDetails::where('property_id', $id)
								->first();
		if(isset($check->pets_allowed) && $check->pets_allowed == 1)
			$pet_fee = env('PET_PRICE');
		else
			$pet_fee = 0;

		return [
				'code' => 1,
				'status' => 'success',
				'data' => [
					"accomodation_fee" => $price,
					"pet_fee" => $pet_fee
					]
				];
	}
	
	public function propertyAreaDetail($id)
	{
		$data = PropertyAreaDetails::where('area_id', $id)
									->where('property_id', env("PROPERTY_ID"))
									->first();
			
		if (is_countable($data) && count($data) == 0) {
            throw new Exception(ucwords('Data Not Found'));
        }
		
		$propData = PropertyDetails::where('property_id', env("PROPERTY_ID"))
									->first();

		if($propData->pets_allowed)
			$pet_flag = $propData->pets_allowed;
		else
			$pet_flag = 0;

		return [
				'code' => 1,
				'status' => 'success',
				'data' => [
					"name" => $data->name,
					"petAllowed" => $pet_flag,
					"maxOccupants" => (int)$data->max_occupants,
					"town" => $data->town
					]
				];
	}
}