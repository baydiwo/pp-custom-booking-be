<?php

namespace App\Http\Controllers;

use App\Jobs\PropertyConcurrentJob;
use App\Jobs\PropertyAvailabilityDateJobNew;
use App\Jobs\PropertyAvailabilityDateJob;
use App\Jobs\PropertyJob;
use App\Jobs\PropertyConcurrentOneCatJob;
use App\Jobs\PropertyDetailsJob;
use App\Models\ModelPropertyJob;
use App\Models\Property;
use App\Models\ModelPropertyAvailability;
use App\Models\PropertyDetails;
use App\Models\PropertyAreaDetails;
use App\Models\AvailabilityDate;
use App\Models\SessionDetails;
use App\Models\BookingSource;
use App\Models\ModelTiming;
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
        $this->authToken = '';//Cache::get('authToken')['token'];
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
	
    public function detail(Request $request, $id)
    {
		$this->webToken = ($request->bearerToken() !== '') ? $request->bearerToken() : '';
		//$this->webToken = ($request->header('authtoken') !== '') ? $request->header('authtoken') : '';
		$now = Carbon::now();
		$checkExpiry = SessionDetails::where('access_token', $this->webToken)->first();
		if(!$checkExpiry)
			 throw new Exception(ucwords('Token is missing or invalid!'));

		$this->authToken = Cache::get('authToken')['token'];
        $api = new ApiController($this->authToken, $this->request);
	   
		$validator = Validator::make(
            $this->params,
            Property::$rules['detail']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
		
		$propertyData = PropertyDetails::select('name')->where('property_id', $id)
            ->first();
		
		$areaData = PropertyAreaDetails::where('property_id', $id)
            ->where('area_id', $this->params['areaId'])
            ->where('category_id', $this->params['categoryId'])
            ->first();
		
		$from = Carbon::parse($this->params['arrivalDate']);
        $to = Carbon::parse($this->params['departureDate']);
		$now = Carbon::now();
        $this->params['rateTypeId'] = $this->rateByDate($from, $to);

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
		
		$diffWeek = $now->diffInWeeks($from);
		$petCount = (isset($this->params['pets']) && $this->params['pets'] > 0) ? $this->params['pets'] : 0;
		$ratestart = Carbon::now();
		$rateQuote = $api->rateQuote($paramsRateQuote);
		$rateend = Carbon::now();
		
        if (isset($rateQuote['Message'])) {
            throw new Exception(ucwords($rateQuote['Message']));
        }
		
        $datefrom = $this->params['arrivalDate'];
        $dateto = $this->params['departureDate'];
		
		$to   = Carbon::createFromFormat('Y-m-d', $this->params['arrivalDate']);
		$from = Carbon::createFromFormat('Y-m-d', $this->params['departureDate']);
        $data['categoryId']      = $areaData['category_id'];
        $data['areaName']    	 = $areaData['name'];
        $data['town']    	 	 = $areaData['external_ref'];
        $data['description']     = $areaData['long_description'];
        $data['imageUrl']     	 = $areaData['image_link'];
        $data['bond']    	 	 = $areaData['bond'];
        $data['petAllowed']      = $areaData['pets_allowed'] == 0 ? false : true;
        $data['petFee']          = $areaData['pets_allowed'] == 0 ? 0 : 150;
        $data['maxOccupants']    = (integer)$areaData['max_occupants'];
        $data['totalRooms']      = (integer)$areaData['total_rooms'];
        $data['totalGuests']     = $this->params['adults'] . ' adults, ' . $this->params['children'] . ' children, ' . $this->params['infants'] . ' infants';
        $data['totalBedrooms']   = (integer)$areaData['total_bedrooms'];
        $data['totalBaths']      = (integer)$areaData['total_baths'];
        $data['nights']          = $to->diffInDays($from);
        $data['accomodation']    = collect($rateQuote['rateBreakdown'])->sum('totalRate');
        $data['totalAmount']     = $data['accomodation'] + ($petCount * $data['petFee']);
		if($diffWeek > 3)
        	$data['dueToday']        = number_format((0.3* $data['accomodation']) * 1.012,2);
		else
        	$data['dueToday']        = number_format($data['totalAmount'] * 1.012,2);
		
		$bs_result = BookingSource::where('status', '1')->get();
		$bs_data = [];
		foreach($bs_result as $bs){
			$bs_data[] = ['id' => $bs->bs_id, 'name' => $bs->bs_name];
		}
		$data['bookingSourceList'] = $bs_data;
		
		if($checkExpiry->booking_id == '')
		{
			$guestGiven = 'PPB';
			$guestSurname = 'Pending';
			$guestPhone = '0417120000';
			$guestId = 21899;
			
			/*$paramSearchGuest = [
				"surname" => $guestSurname,
				"given"   => $guestGiven,
				"mobile"  => $guestPhone
			];
			
			$searchGuest = $api->guestSearch($paramSearchGuest);
			if((count($searchGuest) == 0) || (isset($searchGuest['Message']))) {
				$paramCreateGuest = [
					'addressLine1' => '60 Quandong Parkway',
					'postCode'     => '6210',
					'state'        => 'WA',
					'town'         => 'Halls Head',
					'countryId'    => 13,
					'email'        => 'support@studiojs.com.au',
					'guestGiven'   => $guestGiven,
					'guestSurname' => $guestSurname,
					'mobile'       => $guestPhone,
					'propertyId'   => 1
				];
				
				$createGuest = $api->createGuest($paramCreateGuest);
				if(isset($createGuest['Message'])) {
					throw new Exception(ucwords($createGuest['Message']));
				}
				$guestId = $createGuest['id'];
			} else {
				$searchGuest = collect($searchGuest)->first();
				$guestId = $searchGuest['id'];
			}*/
	
			$expiryDate = Carbon::now()->addMinutes(11);
			$paramPencilData = [
									"id" => 0,
									"areaId" => $this->params['areaId'],
									"arrivalDate" => $datefrom." 14:00:00",
									"categoryId" => $this->params['categoryId'],
									"departureDate" => $dateto." 11:00:00",
									"expiryDate" => $expiryDate,
									"guestId" => $guestId,
									"guestEmail" => "sasikumar@versatile-soft.com",
									"guestGiven" => $guestGiven,
									"guestMobile" => $guestPhone,
									"guestSurname" => $guestSurname,
									"notes" => "This is a note about my test pencil reservation",
									"status" => "Pencil"
								];
	
			$endpoint = 'reservations/pencil';
			$pencilstart = Carbon::now();
			$response = Http::withHeaders([
				'authtoken' => $this->authToken
			])->post(env('BASE_URL_RMS') . $endpoint, $paramPencilData);
			$pencilend = Carbon::now();
	
			if(isset($response['message'])) {
				throw new Exception(ucwords($response['message']));
			}
			
			$data['bookingId'] = (isset($response['id']) && $response['id'] != '') ? $response['id'] : 0;
			
			$model = SessionDetails::where('access_token', $this->webToken)->first();
			$model->booking_id = $data['bookingId'];
			$model->expiry_date = $expiryDate;
			$model->save();
			$data['access_id'] = $model->access_token;
			
			$modelTiming = new ModelTiming();
			$modelTiming->token = $this->webToken;
			$modelTiming->rate_start = $ratestart;
			$modelTiming->rate_end = $rateend;
			$modelTiming->pencil_start = $pencilstart;
			$modelTiming->pencil_end = $pencilend;
			$modelTiming->status = '1';
			$modelTiming->save();
		}
		else
		{
			$data['bookingId'] = (integer)$checkExpiry->booking_id;
			$data['access_id'] = $checkExpiry->access_token;
		}
		return [
			'code' => 1,
			'status' => 'success',
			'data' => $data
		];
    }
	
	public function generateToken(Request $request)
	{
		$validator = Validator::make(
            $this->params,
            [
                'dateFrom'	=> 'required|date_format:Y-m-d',
				'dateTo'	=> 'required|date_format:Y-m-d|after:dateFrom',
				'userIp' 	=> 'required'
            ]
        );
        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
			
		$data['arrival_date'] = $this->params['dateFrom'];
		$data['departure_date'] = $this->params['dateTo'];
		$data['user_ip'] = $this->params['userIp'];
		
		$now = Carbon::now();
		
		$checkToken = SessionDetails::where('arrival_date', $data['arrival_date'])
									->where('departure_date', $data['departure_date'])
									->where('user_ip', $data['user_ip'])
									->where('expiry_date', '>', $now)
									->where('booking_id', '!=', '')->first();
		if(!$checkToken)
		{
			$model = new SessionDetails();
			$token = base64_encode(substr(md5(mt_rand()), 0, 78));
			$data['access_token'] = $token;
			$model->access_token = $token;
			$model->arrival_date = $this->params['dateFrom'];
			$model->departure_date = $this->params['dateTo'];
			$model->user_ip =  $this->params['userIp'];
			$model->save();
			$data['session_id'] = $model->id;
			if($model->save()){
				return [
					'code' => 1,
					'status' => 'success',
					'data' => $data
				];
			}
			else
			{
				return [
					'code' => 1,
					'status' => 'error',
					'message' => 'Token not generated. Please Try Again!'
				];
			}
		}
		else
		{
			$data['access_token'] = $checkToken->access_token;
			$dateNow = strtotime($now);
   			$dateExpiry   = strtotime($checkToken->expiry_date); 
			$data['expiry_time'] = $dateExpiry - $dateNow;
			$data['session_id'] = $checkToken->id;
			return [
				'code' => 1,
				'status' => 'success',
				'data' => $data
			];
		}
	}
	
    public function availabilityGrid()
    {
        $validator = Validator::make(
            $this->params,
            Property::$rules['availability-grid']
        );

        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));

        dispatch(new PropertyJob($this->params['propertyId']));
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
                    3, 6, 7, 8, 9, 10, 11, 20, 23, 24, 25, 26, 27, 28, 29, 31, 32, 33, 34, 35, 36, 37, 41, 42, 43, 44, 45, 47, 48, 49, 50, 51, 52, 64, 65, 66, 68, 70, 103, 104, 105, 106, 107, 108, 109, 110, 111, 114, 115, 116, 117, 118, 119, 120, 122, 123, 124, 125, 126, 127, 128, 130, 131, 132, 133, 134, 136, 144, 145, 146, 147, 148, 149, 150, 156, 157, 158, 159, 160, 161, 162, 163, 164, 165, 166, 167, 168, 169, 170, 171, 173, 174, 175, 176, 177, 178, 179, 181, 183, 184, 185, 186, 187, 188, 189, 216, 217, 219, 220, 221, 222, 263, 264, 265, 266, 267, 268, 269, 270
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
                $content = $response->getReasonPhrase();
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
           // throw new Exception("Date from Cannot Greater From One Year");
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
	
    public function checkAvailabilityConcurrentNew($areaID = 0, $propertyID = 0, $dateFrom = '', $dateTo = '', $postParams = array())
    {
        $nonFeePackageArea = [221, 124, 66, 67, 68, 70];

        $feePackage = 66;
		if(is_countable($postParams) && count($postParams) > 0)
		{
			$this->params = $postParams;
		}
		else
		{
			if($areaID > 0)
				$this->params['areaId'] = $areaID;
			if($propertyID > 0)
				$this->params['propertyId'] = $propertyID;
			if($dateFrom != '')
				$this->params['dateFrom'] = $dateFrom;
			if($dateTo != '')
				$this->params['dateTo'] = $dateTo;
		}			

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
           // throw new Exception("Date from Cannot Greater From One Year");
        }
		
        $diff = $from->diffInDays($to);
        $getRate = $this->rateByDate($from, $to);

        $result = ModelPropertyJob::select('response')
            ->where('property_id', $this->params['propertyId'])
            ->where('date_from', '=', $from)
            ->first();
			
        //$api           = new ApiController($this->authToken, $this->request);
        //$detailArea = $api->detailArea($this->params['areaId']);
        
		$checkAreaDetail =  PropertyAreaDetails::where('area_id', $this->params['areaId'])->first();
		$detailArea['categoryId'] =  $checkAreaDetail->category_id;

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
						if($getRate == $valueDatatempRate['rateId']) { 
							$valTemp = array();
							foreach($valueDatatempRate['dayBreakdown'] as $valRes)
							{
								$recDate = Carbon::parse($valRes['theDate']);
								if($recDate < $to)
									$valTemp[] = $valRes;
							}
							if(is_countable($valTemp) && count($valTemp) > 0)
								$valueDatatempRate['dayBreakdown'] = $valTemp;
							
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
			{
				$diffDays = $from->diffInDays($to);
				if($diffDays < 3)
				{
					$this->params['dateTo'] = Carbon::parse($to)->addDays(1)->format('Y-m-d');
					return $this->checkAvailabilityConcurrentNew(0, 0, '', '', $this->params);
				}
				else
					throw new Exception("Data not available for selected date");
			}
		}
    }
	
	private function fetchDataRecursive($propertyId, $from, $to, $categoryId, $getRate)
	{
		$result = ModelPropertyJob::select('response')
            ->where('property_id', $propertyId)
            ->where('date_from', '=', $from)
            ->first();

        $new = json_decode($result->response);
        $tempRates = $tempRate = $tempData = [];
        foreach ($new as $keynew => $valuenew) {
            $json = json_decode($valuenew, true);
            $dataRate = collect($json['categories'])->where('categoryId', $categoryId)->values()->first();
            array_push($tempRate, $dataRate);
        }

		foreach ($tempRate as $valuetempRate) {
			foreach ($valuetempRate['rates'] as $valueDatatempRate) {
				if($getRate == $valueDatatempRate['rateId']) {
					$tempRates = $valueDatatempRate['dayBreakdown'];
				}
			}
		}

		if(is_countable($tempRates) && count($tempRates) > 0)
		{
			$lday=count($tempRates);
			if(isset($tempRates[$lday-1]['theDate']))
			{
				$endDate = Carbon::parse($tempRates[$lday-1]['theDate']);
				if($endDate < $to)
				{
					$tempData = $this->fetchDataRecursive($propertyId, $endDate, $to, $categoryId, $getRate);
				}
			}
		}
		array_shift($tempData);
		return array_merge($tempRates,$tempData);
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
			dispatch(new PropertyAvailabilityDateJob($this->params['propertyId']));
		else if(isset($this->params['jobId']) && $this->params['jobId'] == 2)
			dispatch(new PropertyDetailsJob($this->params['propertyId']));
		else if(isset($this->params['jobId']) && $this->params['jobId'] == 3)
			dispatch(new PropertyAvailabilityDateJobNew($this->params['propertyId']));
		else
			dispatch(new PropertyConcurrentJob($this->params['propertyId']));
		
        return [
            'code' => 1,
            'status' => 'success',
            'data' => [],
            'message' => "Data Has Been Saved in Cache"
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
	
	public function getAvailabilityDatesByArea()
	{
		$validator = Validator::make(
            $this->params,
            [
				'areaId'     => 'required|integer'
            ]
        );
		
        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
		
		$data = PropertyAreaDetails::where('area_id', $this->params['areaId'])
									->where('property_id', env("PROPERTY_ID"))
									->first();
			
		if (is_countable($data) && count($data) == 0) {
            throw new Exception(ucwords('Data Not Found'));
        }

		if($data->category_id)
			$category_id = $data->category_id;
		else
			$category_id = 0;
			
		$cDate = Carbon::now()->format('Y-m-01');
		$startDate = Carbon::createFromFormat('Y-m-d', $cDate)->addDays(-1)->format('Y-m-d');
		$nxtYear = Carbon::now()->addYear()->format('Y-m-d');
		$nextYear = Carbon::parse($nxtYear)->endOfMonth()->format('Y-m-d');
		$dateAvail = AvailabilityDate::select('date_from')->where('category_id', $category_id)
									->where('date_from', '>=', $startDate)
									->where('date_from', '<=', $nextYear)
									->where('available_area', 1)
									->orderBy('date_from', 'Asc')
									->get();
									
		$availDates = [];
		foreach($dateAvail as $result)
		{
			$availDates[] = $result['date_from'];
		}
		return [
				'code' => 1,
				'status' => 'success',
				'data' => [
					"available_dates" => $availDates
					]
				];
	}
	
	public function getAvailabilityDatesADByArea()
	{
		$validator = Validator::make(
            $this->params,
            [
				'areaId'     => 'required|integer'
            ]
        );
		
        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
		
		$data = PropertyAreaDetails::where('area_id', $this->params['areaId'])
									->where('property_id', env("PROPERTY_ID"))
									->first();
			
		if (is_countable($data) && count($data) == 0) {
            throw new Exception(ucwords('Data Not Found'));
        }

		if($data->category_id)
			$category_id = $data->category_id;
		else
			$category_id = 0;
			
		$startDate = Carbon::now()->format('Y-m-01');
		$nxtYear = Carbon::now()->addYear()->format('Y-m-d');
		$nextYear = Carbon::parse($nxtYear)->endOfMonth()->format('Y-m-d');
		$dateAvail = AvailabilityDate::select('date_from')->where('category_id', $category_id)
									->where('date_from', '>=', $startDate)
									->where('date_from', '<=', $nextYear)
									->where('available_area', 1)
									->orderBy('date_from', 'Asc')
									->get();
									
		$availDates = [];
		foreach($dateAvail as $result)
		{
			$from_date = $result['date_from'].' 14:30:00';
			$to_date = Carbon::createFromFormat('Y-m-d', $result['date_from'])->addDays(1)->format('Y-m-d 10:30:00');
			$availDates[] = ['arrival_date' => $from_date, 'departure_date' => $to_date];//$result['date_from'];
		}
		return [
				'code' => 1,
				'status' => 'success',
				'data' => [
					"available_dates" => $availDates
					]
				];
	}
	
	public function getAvailabilityAreasByDate()
	{
		$validator = Validator::make(
            $this->params,
            [
				'dateFrom'   => 'required|date_format:Y-m-d',
				'dateTo'     => 'required|date_format:Y-m-d|after:dateFrom'
            ]
        );
		
        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
		
		$this->params['dateTo'] = Carbon::createFromFormat('Y-m-d', $this->params['dateTo'])->addDays(-1)->format('Y-m-d');
			
		$dateAvail = AvailabilityDate::select('area_details.area_id', 'availability_date.date_from')->whereBetween('availability_date.date_from', [$this->params['dateFrom'],$this->params['dateTo']])
										->leftJoin('area_details', 'area_details.category_id', '=', 'availability_date.category_id')
										->where('availability_date.available_area', 1)
										->orderBy('area_details.area_id','ASC')
										->get();
		
			
		$availCategories = $availAreas = [];
		foreach($dateAvail as $dateResult)
		{
			$availCategories[$dateResult['area_id']][] = $dateResult['date_from'];
		}
		$from = Carbon::parse($this->params['dateFrom']);
        $to = Carbon::parse($this->params['dateTo']);
        $diff = $from->diffInDays($to) + 1;

		foreach($availCategories as $key => $areaRes)
		{
			if(is_countable($areaRes) && count($areaRes) == $diff)
				$availAreas[] = $key;
		}
		
		return [
				'code' => 1,
				'status' => 'success',
				'data' => [
					"available_areas" => $availAreas
					]
				];
	}
	
	public function test()
	{
		$paramData = [
										"contact" => [
																	"email" => "sasikumar@versatile-soft.com",
																	"firstName" => "Sasi",
																	"lastName" => "Kumar",
																	"phone" => "0417123010"
																]
								];
		$endpoint = 'https://privateproperties.api-us1.com/api/3/contacts';
		$response = Http::withHeaders([
			'Api-Token' => 'f7f7f7d0870e23659fb522b332627d277e00d32114b891b5c8788e9d3009700b0de72322'
		])->post($endpoint, $paramData);
		
		print_r($response);
	}
}