<?php

namespace App\Http\Controllers;

use App\Http\Helpers\Constant;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\BookingDetails;
use App\Models\SessionDetails;
use App\Models\PropertyAreaDetails;

class BookingController
{
    private $authToken;
    private $request;
    private $params;

    public function __construct(Request $request)
    {
        $this->authToken = Cache::get('authToken')['token'];
        $this->request = $request;
        $this->params  = $request->all();
		$this->webToken = ($request->bearerToken() !== '') ? $request->bearerToken() : '';//($request->header('authtoken') !== '') ? $request->header('authtoken') : '';
		$now = Carbon::now();
		$checkExpiry = SessionDetails::where('access_token', $this->webToken)->first();
		if(!$checkExpiry || ($checkExpiry->expiry_date < $now && $checkExpiry->status == 0))
			 throw new Exception(ucwords('Transaction Timed-out! Please try again.'));
		else
			$this->booking_id = $checkExpiry->booking_id;
    }

    public function create()
    {
        $api = new ApiController($this->authToken, $this->request);
        $validator = Validator::make(
            $this->params,
            [
                'dateFrom'     	=> 'required|date_format:Y-m-d',
                'dateTo'  		=> 'required|date_format:Y-m-d',
                'surname'       => 'required',
                'given'         => 'required',
                'email'         => 'required|email',
                'adults'        => 'required|integer',
                'areaId'        => 'required|integer',
                'categoryId'    => 'required|integer',
                'children'      => 'required|integer',
                'infants'       => 'required|integer',
                'address'       => 'required',
                'state'         => 'required',
                'town'          => 'required',
                'countryId'     => 'required|integer',
                'nights'        => 'required|integer',
                'phone'         => 'required',
                'postCode'      => 'required',
                'bookingSource' => 'required|integer'
            ]
        );
		
        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
				
		$from = Carbon::parse($this->params['dateFrom']);
        $to = Carbon::parse($this->params['dateTo']);
		$rate_type_id = $this->rateByDate($from, $to);
		
		/*$diffDays = $from->diffInDays($to);
		
		$loop = (int)($diffDays/13);
		if($diffDays%13 > 0)
			$loop++;
			
		for($i=1; $i <= $loop; $i++)
		{
			$t = 13;
			$startdate = (string)$from;
			$enddate = $from->addDays($t)->format('Y-m-d');
			if($i == $loop && $enddate > $to)
				$enddate = $to;
			$paramMinNight = [
				'categoryIds' => [$this->params['categoryId']],
				'dateFrom'    => $startdate,
				'dateTo'      => $enddate,
				'propertyId'  => 1,
				'rateIds'     => [$rate_type_id]
			];
       		$minNight = $api->availabilityrategrid($paramMinNight);
			
			if (isset($minNight)) {
				if (isset($minNight['Message'])) {
					throw new Exception(ucwords($minNight['Message']));
				}
				else if (empty($minNight['categories'][0]['rates'])) {
					throw new Exception(ucwords('Rate Not Found'));
				}
				else {
					foreach($minNight['categories'][0]['rates'][0]['dayBreakdown'] as $rate_check)
					{
						if($rate_check['availableAreas'] == 0)
							throw new Exception(ucwords('Booking not available for the selected dates!'));//Minimum Night Not Found'));
					}
				}
			} else if (!$minNight) {
				throw new Exception(ucwords('Booking not available for the selected dates!'));//Minimum Night Not Found'));
			}
		}*/
		
        $paramSearchGuest = [
            "surname" => $this->params['surname'],
            "given"   => $this->params['given'],
            "mobile"  => $this->params['phone'],
        ];
		
        $searchGuest = $api->guestSearch($paramSearchGuest);
        if((count($searchGuest) == 0) || (isset($searchGuest['Message']))) {
            $paramCreateGuest = [
                'addressLine1' => $this->params['address'],
                'postCode'     => $this->params['postCode'],
                'state'        => $this->params['state'],
                'town'         => $this->params['town'],
                'countryId'    => $this->params['countryId'],
                'email'        => $this->params['email'],
                'guestGiven'   => $this->params['given'],
                'guestSurname' => $this->params['surname'],
                'mobile'       => $this->params['phone'],
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
        }
		
		/*$expiryDate = Carbon::now()->addMinutes(11);
		$paramPencil = [
							"id"			=> 0,
							"areaId"		=> $this->params['areaId'],
							"arrivalDate"	=> $this->params['dateFrom'].' 14:00:00',
							"categoryId"	=> $this->params['categoryId'],
							"departureDate" => $this->params['dateTo'].' 11:00:00',
							"expiryDate" 	=> $expiryDate,
							"guestEmail" 	=> $this->params['email'],
							"guestGiven" 	=> $this->params['given'],
							"guestSurname" 	=> $this->params['surname'],
							"guestMobile" 	=> $this->params['phone'],
							"guestId"		=> $guestId
						];
							
		$endpoint = 'reservations/pencil';

		$response = Http::withHeaders([
			'authtoken' => $this->authToken
		])->post(env('BASE_URL_RMS') . $endpoint, $paramPencil);

		if(isset($response['message'])) {
			throw new Exception(ucwords($response['message']));
		}*/
		
		$booking_id = $this->booking_id;//(isset($response['id']) && $response['id'] != '') ? $response['id'] : 0;
		
		$model = new BookingDetails();
		$model->arrival_date   	= $this->params['dateFrom'].' 14:00:00';
		$model->departure_date 	= $this->params['dateTo'].' 11:00:00';
		$model->surname			= $this->params['surname'];
		$model->given         	= $this->params['given'];
		$model->email         	= $this->params['email'];
		$model->adults        	= $this->params['adults'];
		$model->area_id       	= $this->params['areaId'];
		$model->category_id   	= $this->params['categoryId'];
		$model->children      	= $this->params['children'];
		$model->infants       	= $this->params['infants'];
		$model->notes     		= $this->params['notes'];
		$model->address       	= $this->params['address'];
		$model->rate_type_id  	= $rate_type_id;
		$model->state         	= $this->params['state'];
		$model->town         	= $this->params['town'];
		$model->country_id    	= $this->params['countryId'];
		$model->nights        	= $this->params['nights'];
		$model->phone         	= $this->params['phone'];
		$model->post_code     	= $this->params['postCode'];
		$model->pets      		= (isset($this->params['pets']) && $this->params['pets'] != '') ? $this->params['pets'] : 0;
		$model->accomodation_fee= $this->params['accomodationFee'];
		$model->pet_fee     	= $this->params['petFee'];
		$model->due_today    	= $this->params['dueToday'];
		$model->guest_id		= $guestId;
		$model->booking_id		= $booking_id;
		$model->booking_source_id = $this->params['bookingSource'];
		$model->booking_status	= '0';
		$model->save();
		$booking_details_id = $model->id;
		
		$response = array();
		$response = [
						"adults" => $this->params['adults'],
						"bookingSourceName" => "",
						"bookingSourceId" => 200,
						"children" => $this->params['children'],
						"companyId" => 0,
						"discountId" => 0,
						"groupAllotmentId" => 0,
						"groupReservationId" => 0,
						"infants" => $this->params['infants'],
						"marketSegmentId" => 0,
						"notes" => $this->params['notes'],
						"otaNotes" => "",
						"onlineConfirmationId" => 0,
						"resTypeId" => 0,
						"subMarketSegmentId" => 0,
						"travelAgentId" => 0,
						"userDefined1" => "",
						"userDefined2" => "",
						"userDefined3" => "",
						"userDefined4" => "",
						"userDefined5" => "",
						"userDefined6" => "",
						"userDefined7" => "",
						"userDefined8" => "",
						"userDefined9" => "",
						"userDefined10" => "",
						"userDefined11" => false,
						"userDefined12" => false,
						"userDefined13" => false,
						"userDefined14" => "1900-01-01 00:00:00",
						"userDefined15" => "1900-01-01 00:00:00",
						"voucherId" => "",
						"wholesalerId" => 0,
						"id" => $booking_details_id,
						"bookingId" => $booking_id,
						"areaId" => $this->params['areaId'],
						"arrivalDate" => $this->params['dateFrom']." 14:00:00",
						"cancelledDate" => "1900-01-01 00:00:00",
						"categoryId" => $this->params['categoryId'],
						"departureDate" => $this->params['dateTo']." 11:00:00",
						"guestId" => $guestId,
						"rateTypeId" => $rate_type_id,
						"rateTypeName" => ($rate_type_id+1)." Night OTA",
						"bookingSource" => $this->params['bookingSource'],
						"status" => "Unconfirmed"
					];
		
        return [
            'code' => 1,
            'status' => 'success',
            'data' => $response
        ];
    }
	
    public function detail($id)
    {
        $api = new ApiController($this->authToken, $this->request);
		$reservation = array();
		$booking_details = BookingDetails::where('id', $id)->first();
		
		$reservation['arrivalDate'] 	= $booking_details['arrival_date'];
		$reservation['departureDate']	= $booking_details['departure_date'];
		$reservation['adults']			= $booking_details['adults'];
		$reservation['areaId']			= $booking_details['area_id'];
		$reservation['categoryId']		= $booking_details['category_id'];
		$reservation['children']		= $booking_details['children'];
		$reservation['infants']			= $booking_details['infants'];
		$reservation['notes']			= $booking_details['notes'];
		$reservation['rateTypeId']		= $booking_details['rate_type_id'];
		$reservation['nights']			= $booking_details['nights'];
		$reservation['surname']			= $booking_details['surname'];
		$reservation['given']			= $booking_details['given'];
		$reservation['address']			= $booking_details['address'];
		$reservation['email']			= $booking_details['email'];
		$reservation['countryId']		= $booking_details['country_id'];
		$reservation['postCode']		= $booking_details['post_code'];
		$reservation['phone']			= $booking_details['phone'];
		$reservation['state']			= $booking_details['state'];
		$reservation['town']			= $booking_details['town'];
		$reservation['bookingSource']	= $booking_details['booking_source_id'];
		$reservation['accomodation']	= $booking_details['accomodation_fee'];
		$reservation['petFee']			= $booking_details['pet_fee'];
		$reservation['pets']			= $booking_details['pets'];
		$reservation['totalAmount']		= $booking_details['accomodation_fee'] + ($booking_details['pets'] * $booking_details['pet_fee']);
		$reservation['dueToday']		= $booking_details['due_today'];
		
		$areaData = PropertyAreaDetails::where('area_id', $booking_details['area_id'])
										->where('category_id', $booking_details['category_id'])
										->first();
		
        $areaDetails['categoryId']      = $areaData['category_id'];
        $areaDetails['areaName']    	= $areaData['name'];
        $areaDetails['town']    	 	= $areaData['town'];
        $areaDetails['description']     = $areaData['long_description'];
        $areaDetails['imageUrl']     	= $areaData['image_link'];
		$areaDetails['bond']        	= $areaData['bond'];
        $areaDetails['petAllowed']      = $areaData['pets_allowed'] == 0 ? false : true;
        $areaDetails['petFee']          = $booking_details['pet_fee'];
        $areaDetails['maxOccupants']    = (integer)$areaData['max_occupants'];
        $areaDetails['totalRooms']      = (integer)$areaData['total_rooms'];
        $areaDetails['totalGuests']     = $booking_details['adults'] . ' adults, ' . $booking_details['children'] . ' children, ' . $booking_details['infants'] . ' infants';
        $areaDetails['totalBedrooms']   = (integer)$areaData['total_bedrooms'];
        $areaDetails['totalBaths']      = (integer)$areaData['total_baths'];
        $areaDetails['nights']          = $booking_details['nights'];
        $areaDetails['accomodation']    = $booking_details['accomodation_fee'];
        $areaDetails['totalAmount']     = $booking_details['accomodation_fee'] + $booking_details['pet_fee'];
		$areaDetails['dueToday']        = $booking_details['due_today'];
		
		$data = ['reservationDetails' => $reservation, 'areaDetails' => $areaDetails];
		
        return [
            'code' => 1,
            'status' => 'success',
            'data' => $data
        ];
    }

    public function update($booking_id)
    {
        $api = new ApiController($this->authToken, $this->request);
        $validator = Validator::make(
            $this->params,
            [
                'dateFrom'     	=> 'required|date_format:Y-m-d',
                'dateTo'  		=> 'required|date_format:Y-m-d',
                'surname'       => 'required',
                'given'         => 'required',
                'email'         => 'required|email',
                'adults'        => 'required|integer',
                'children'      => 'required|integer',
                'infants'       => 'required|integer',
                'address'       => 'required',
                'state'         => 'required',
                'town'          => 'required',
                'countryId'     => 'required|integer',
                'nights'        => 'required|integer',
                'phone'         => 'required',
                'postCode'      => 'required',
                'bookingSource' => 'required|integer'
            ]
        );
		
        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
		
		$paramSearchGuest = [
            "surname" => $this->params['surname'],
            "given"   => $this->params['given'],
            "mobile"  => $this->params['phone'],
        ];
		
        $searchGuest = $api->guestSearch($paramSearchGuest);
        if((count($searchGuest) == 0) || (isset($searchGuest['Message']))) {
            $paramCreateGuest = [
                'addressLine1' => $this->params['address'],
                'postCode'     => $this->params['postCode'],
                'state'        => $this->params['state'],
                'town'         => $this->params['town'],
                'countryId'    => $this->params['countryId'],
                'email'        => $this->params['email'],
                'guestGiven'   => $this->params['given'],
                'guestSurname' => $this->params['surname'],
                'mobile'       => $this->params['phone'],
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
        }
		
		$booking_details = BookingDetails::where('id', $booking_id)->first();

		$booking_details->arrival_date   	= $this->params['dateFrom'];
		$booking_details->departure_date 	= $this->params['dateTo'];
		$booking_details->surname			= $this->params['surname'];
		$booking_details->given         	= $this->params['given'];
		$booking_details->email         	= $this->params['email'];
		$booking_details->adults        	= $this->params['adults'];
		$booking_details->children      	= $this->params['children'];
		$booking_details->infants       	= $this->params['infants'];
		$booking_details->notes     		= $this->params['notes'];
		$booking_details->address       	= $this->params['address'];
		$booking_details->state         	= $this->params['state'];
		$booking_details->town         		= $this->params['town'];
		$booking_details->country_id    	= $this->params['countryId'];
		$booking_details->nights        	= $this->params['nights'];
		$booking_details->phone         	= $this->params['phone'];
		$booking_details->post_code     	= $this->params['postCode'];
		$booking_details->pets      		= (isset($this->params['pets']) && $this->params['pets'] != '') ? $this->params['pets'] : 0;
		$booking_details->accomodation_fee	= $this->params['accomodationFee'];
		$booking_details->pet_fee     		= $this->params['petFee'];
		$booking_details->due_today    		= $this->params['dueToday'];
		$booking_details->booking_source_id	= $this->params['bookingSource'];
		$booking_details->guest_id			= $guestId;
		
		$booking_details->save();
		
        return [
            'code' => 1,
            'status' => 'success',
            'data' => ["id" => $booking_id]
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
}
