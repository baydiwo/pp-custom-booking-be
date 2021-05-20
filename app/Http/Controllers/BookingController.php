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
							//'resTypeId'  	=> $this->params['resTypeId'],
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
		$model->guest_id		= $guestId;
		
        /*$endpoint = 'reservations?ignoreMandatoryFieldWarnings=true';

        $response = Http::withHeaders([
            'authtoken' => $this->authToken
        ])->post(env('BASE_URL_RMS') . $endpoint, $paramDetails);

        if(isset($response['Message'])) {
            throw new Exception(ucwords($response['Message']));
        }*/
		
		//$model->booking_id = (isset($response['id']) && $response['id'] != '') ? $response['id'] : 0;
		$model->booking_id = rand(6,100000);
		$response = array();//temporarily added when reserbvation API was blocked
		$model->save();
		
        return [
            'code' => 1,
            'status' => 'success',
            'data' => [],//$response->json(),
			'postData' => $this->params
        ];
    }

    public function create_old()
    {
        $api = new ApiController($this->authToken, $this->request);
        $validator = Validator::make(
            $this->params,
            [
                'arrivalDate'     => 'required|date_format:Y-m-d H:i:s',
                'departureDate'   => 'required|date_format:Y-m-d H:i:s',
                'surname'         => 'required',
                'given'           => 'required',
                'adults'          => 'required|integer',
                'areaId'          => 'required|integer',
                'categoryId'      => 'required|integer',
                'children'        => 'required|integer',
                'infants'         => 'required|integer',
                'rateTypeId'      => 'required|integer',
                'state'           => 'required',
                'town'            => 'required',
                'address'         => 'required',
                'countryId'       => 'required|integer',
                'nights'          => 'required|integer',
                'email'           => 'required|email',
                'phone'           => 'required',
                'postCode'        => 'required',

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
            ];

            $createGuest = $api->createGuest($paramCreateGuest);
            if(isset($createGuest['Message'])) {
                throw new Exception(ucwords($createGuest['Message']));
            }
            $this->params['guestId'] = $createGuest['id'];
        } else {
            $searchGuest = collect($searchGuest)->first();
            $this->params['guestId'] = $searchGuest['id'];
        }

        $this->params['bookingSourceId'] = 200;

        $endpoint = 'reservations?ignoreMandatoryFieldWarnings=true';

        $response = Http::withHeaders([
            'authtoken' => $this->authToken
        ])->post(env('BASE_URL_RMS') . $endpoint, $this->params);

        if(isset($response['Message'])) {
            throw new Exception(ucwords($response['Message']));
        }
        return [
            'code' => 1,
            'status' => 'success',
            'data' => $response->json()
        ];
    }

    public function detail($id) 
    {
        $dataResponse = [];
        $api = new ApiController($this->authToken, $this->request);
        $detailReservation = $api->detailReservation($id);
        if (isset($detailReservation['Message'])) {
            throw new Exception('Data Reservation Not Found');
        }

        $detailGuest = $api->guestDetail($detailReservation['guestId']);
        if (isset($detailGuest['Message'])) {
            throw new Exception('Data Guest Not Found');
        }
        $dataGuest = collect($detailGuest)->first();
        $dataGuest['propertyId'] = 1;
        // if($dataGuest['propertyId'] == 0) {
        //     throw new Exception('Property Guest Not Found');
        // }

        $detailSetting = $api->detailPropertySetting($dataGuest['propertyId']);
        if (isset($detailSetting['Message'])) {
            throw new Exception(ucwords($detailSetting['Message']));
        }
        $paramsRateQuote = [
            'adults'        => $detailReservation['adults'],
            'areaId'        => $detailReservation['areaId'],
            'arrivalDate'   => $detailReservation['arrivalDate'],
            'categoryId'    => $detailReservation['categoryId'],
            'children'      => $detailReservation['children'],
            'departureDate' => $detailReservation['departureDate'],
            'infants'       => $detailReservation['infants'],
            'propertyId'    => $dataGuest['propertyId'],
            'rateTypeId'    => $detailReservation['rateTypeId'],
        ];

        $rateQuote = $api->rateQuote($paramsRateQuote);
        if (isset($rateQuote['Message'])) {
            throw new Exception(ucwords($rateQuote['Message']));
        }


        $arrivalDate   = Carbon::createFromFormat('Y-m-d H:s:i', $detailReservation['arrivalDate']);
        $departureDate = Carbon::createFromFormat('Y-m-d H:s:i', $detailReservation['departureDate']);

        $dataResponse['arrivalDate']   = $detailReservation['arrivalDate'];
        $dataResponse['departureDate'] = $detailReservation['departureDate'];
        $dataResponse['adults']        = $detailReservation['adults'];
        $dataResponse['areaId']        = $detailReservation['areaId'];
        $dataResponse['categoryId']    = $detailReservation['categoryId'];
        $dataResponse['children']      = $detailReservation['children'];
        $dataResponse['infants']       = $detailReservation['infants'];
        $dataResponse['notes']         = $detailReservation['notes'];
        $dataResponse['rateTypeId']    = $detailReservation['rateTypeId'];
        $dataResponse['nights']        = $arrivalDate->diffInDays($departureDate);
        $dataResponse['surname']       = $dataGuest['guestSurname'];
        $dataResponse['given']         = $dataGuest['guestGiven'];
        $dataResponse['address']       = $dataGuest['addressLine1'];
        $dataResponse['email']         = $dataGuest['email'];
        $dataResponse['countryId']     = $dataGuest['countryId'];
        $dataResponse['postCode']      = $dataGuest['postCode'];
        $dataResponse['phone']         = $dataGuest['mobile'];
        $dataResponse['state']         = $dataGuest['state'];
        $dataResponse['town']          = $dataGuest['town'];
        $dataResponse['accomodation']  = collect($rateQuote['rateBreakdown'])->sum('totalRate');
        $dataResponse['petFee']        = $detailSetting['petsAllowed'] == false ? 0 : 150;
        $dataResponse['totalAmount']   = $dataResponse['accomodation'] + $dataResponse['petFee'];
        $dataResponse['dueToday']      = $rateQuote['firstNightRate'];

        return [
            'code'   => 1,
            'status' => 'success',
            'data'   => $dataResponse
        ];
    }
}
