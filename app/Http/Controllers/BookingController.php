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
                'rateTypeId'    => 'required|integer',
                'state'         => 'required',
                'town'          => 'required',
                'countryId'     => 'required|integer',
                'nights'        => 'required|integer',
                'phone'         => 'required',
                'postCode'      => 'required'
            ]
        );
		
        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));

        /*$paramSearchGuest = [
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
        }*/
		$guestId = 19439;//temporarily added when Guest API was blocked
		
		$paramDetails = [
							'arrivalDate'   => $this->params['dateFrom'],
							'departureDate' => $this->params['dateTo'],
							'surname'		=> $this->params['surname'],
							'given'         => $this->params['given'],
							'email'         => $this->params['email'],
							'adults'        => $this->params['adults'],
							'areaId'       	=> $this->params['areaId'],
							'categoryId'   	=> $this->params['categoryId'],
							'children'      => $this->params['children'],
							'infants'       => $this->params['infants'],
							'notes'      	=> $this->params['notes'],
							'address'       => $this->params['address'],
							'rateTypeId'  	=> $this->params['rateTypeId'],
							'state'         => $this->params['state'],
							'town'          => $this->params['town'],
							'countryId'    	=> $this->params['countryId'],
							'nights'        => $this->params['nights'],
							'phone'         => $this->params['phone'],
							'postCode'     	=> $this->params['postCode'],
							'pets'      	=> (isset($this->params['pets']) && $this->params['pets'] != '') ? $this->params['pets'] : 0,
							'guestId'		=> $guestId,
							'bookingSourceId' => 200
						];
		$booking_id = rand(6,100000);

		$model = new BookingDetails();
		$model->arrival_date   	= $this->params['dateFrom'];
		$model->departure_date 	= $this->params['dateTo'];
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
		$model->rate_type_id  	= $this->params['rateTypeId'];
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
		
        /*$endpoint = 'reservations?ignoreMandatoryFieldWarnings=true';

        $response = Http::withHeaders([
            'authtoken' => $this->authToken
        ])->post(env('BASE_URL_RMS') . $endpoint, $paramDetails);

        if(isset($response['Message'])) {
            throw new Exception(ucwords($response['Message']));
        }
		
		$model->booking_id = (isset($response['id']) && $response['id'] != '') ? $response['id'] : 0;*/
		
		// Start - temporarily added when reserbvation API was blocked
		$model->booking_id = $booking_id;
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
						"id" => $booking_id,
						"accountId" => 55262,
						"areaId" => $this->params['areaId'],
						"arrivalDate" => $this->params['dateFrom']." 15:00:00",
						"cancelledDate" => "1900-01-01 00:00:00",
						"categoryId" => $this->params['categoryId'],
						"departureDate" => $this->params['dateTo']." 10:30:00",
						"guestId" => 19439,
						"rateTypeId" => $this->params['rateTypeId'],
						"rateTypeName" => ($this->params['rateTypeId']+1)." Night OTA",
						"status" => "Unconfirmed"
					];
		// End - temporarily added when reserbvation API was blocked
		$model->save();
		
        return [
            'code' => 1,
            'status' => 'success',
            'data' => $response//->json() // temporarily commented, as the reserbvation API was blocked
        ];
    }
	
    public function detail($id)
    {
        $api = new ApiController($this->authToken, $this->request);
		$reservation = array();
		$booking_details = BookingDetails::where('booking_id', $id)->orWhere('id', $id)->first();
		
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
		$reservation['accomodation']	= $booking_details['accomodation_fee'];
		$reservation['petFee']			= $booking_details['pet_fee'];
		$reservation['pets']			= $booking_details['pets'];
		$reservation['totalAmount']		= $booking_details['accomodation_fee'] + $booking_details['pet_fee'];
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
                'postCode'      => 'required'
            ]
        );
		
        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
		
		$booking_details = BookingDetails::where('booking_id', $booking_id)->first();
		$guestId = 19439;//temporarily added when Guest API was blocked

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
		$booking_details->guest_id			= $guestId;
		
		$booking_details->save();
		
        return [
            'code' => 1,
            'status' => 'success',
            'data' => ["id" => $booking_id] // temporarily commented, as the reserbvation API was blocked
        ];
    }
}
