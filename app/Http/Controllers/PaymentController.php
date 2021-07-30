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
use LVR\CreditCard\CardCvc;
use LVR\CreditCard\CardExpirationMonth;
use LVR\CreditCard\CardExpirationYear;
use LVR\CreditCard\CardNumber;
use LVR\CreditCard\Cards\Card;
use App\Models\BookingDetails;

class PaymentController
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

    public function payment($reservationId)
    {
        $api = new ApiController($this->authToken, $this->request);
		
		/*$booking_details = BookingDetails::where('booking_id', $reservationId)->orWhere('id', $reservationId)->first();
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
						
		$endpoint = 'reservations?ignoreMandatoryFieldWarnings=true';

        $response = Http::withHeaders([
            'authtoken' => $this->authToken
        ])->post(env('BASE_URL_RMS') . $endpoint, $paramDetails);

        if(isset($response['Message'])) {
            throw new Exception(ucwords($response['Message']));
        }
		
		$model->booking_id = (isset($response['id']) && $response['id'] != '') ? $response['id'] : 0;
		
		
        /*$detailReservation = $api->detailReservation($reservationId);
        if (isset($detailReservation['Message'])) {
            throw new Exception('Data Reservation Not Found');
        }*/
        $validator = Validator::make(
            $this->params,
            [
                'cardHolderName' => 'required',
                // 'cardNumber' => ['required', new CardNumber],
                // 'dateExpiryMonth' => ['required', new CardExpirationMonth($this->request->get('dateExpiryYear'))],
                // 'dateExpiryYear' => ['required', new CardExpirationYear($this->request->get('dateExpiryMonth'))],
                // 'cvc' => ['required', new CardCvc($this->request->get('card_number'))],
                'cardNumber'        => 'required',
                'dateExpiryMonth'   => 'required',
                'dateExpiryYear'    => 'required',
                'cvc'               => 'required'
            ]
        );
        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
        
		$booking_details = BookingDetails::select('pets', 'accomodation_fee', 'pet_fee', 'booking_id', 'email')->where('booking_id', $reservationId)->orWhere('id', $reservationId)->first();
		if(!$booking_details)
			throw new Exception(ucwords('Booking details not found!'));
		else
			$amount = $booking_details['accomodation_fee'] + ($booking_details['pets'] * $booking_details['pet_fee']);
		$amount = number_format($amount,2);
		
        $paramsCreatePurchaseSessions = [
            "type"                => "purchase",
            "amount"              => $amount,
            "currency"            => env('CURRENCY'),
            "merchantReference"   => "Private Properties",
            "storeCard"           => false,
            "storeCardIndicator"  => "single",
            "callbackUrls" => [
                "approved" => "https://pp-booking-apidev.herokuapp.com/success",
                "declined" => "https://pp-booking-apidev.herokuapp.com/fail", 
                "cancelled" => "https://pp-booking-apidev.herokuapp.com/cancel", 
                //"approved" => "https://pp-booking-staging.netlify.app/success",
                //"declined" => "https://pp-booking-staging.netlify.app/fail", 
                //"cancelled" => "https://pp-booking-staging.netlify.app/cancel", 
            ],
            "notificationUrl" => "https://pp-booking-apidev.herokuapp.com/success"
        ];
		
        $createPurchaseSessions = $api->windCaveCreatePurchaseSessions($paramsCreatePurchaseSessions);
        if(isset($createPurchaseSessions['errors'])) {
            $messageErrorPurchaseSessions = "";
            foreach ($createPurchaseSessions['errors'] as $value) {
                $messageErrorPurchaseSessions .= $value['message'];
                $messageErrorPurchaseSessions .= ". ";
            }
            throw new Exception($messageErrorPurchaseSessions);
        }

        $ajaxPostUrl = $createPurchaseSessions['links'][3]['href'];
        $paramPostCardData = [
            'card' => [
                'cardHolderName'    => $this->params['cardHolderName'],
                'cardNumber'        => $this->params['cardNumber'],
                'dateExpiryMonth'   => $this->params['dateExpiryMonth'],
                'dateExpiryYear'    => $this->params['dateExpiryYear'],
                'cvc2'              => $this->params['cvc']
            ]
        ];

        //get account property guest
        /*$accountProperty = $api->guestAccountProperty($detailReservation['guestId']);
        if ((isset($accountProperty['Message'])) || (count($accountProperty) == 0)) {
            throw new Exception('Account Property Guest Not Found');
        }

        $accountPropertyId = $accountProperty[0]['id'];*/

        //do payment
        $postCardData = $api->windCavePostCardData($ajaxPostUrl, $paramPostCardData);
		
		if(isset($postCardData['links'][0]['rel']) && $postCardData['links'][0]['rel'] == '3DSecure')
		{
			return [
				'code'    => 1,
				'status'  => 'success',
				'data'    => $postCardData['links'][0]['href'],
				'message' => "Waiting for 3D Secure Code verification"
			];
		}
        $cardId = $postCardData['id'];
        $windCaveDetail = $api->windCaveTransactionDetail($cardId);
        if(isset($windCaveDetail['errors'])) {
            throw new Exception('Wind Cave Transaction Detail Not Found');
        }
		
		if($windCaveDetail['transactions'][0]['responseText'] == 'APPROVED') {
			return [
				'code'    => 1,
				'status'  => 'success',
				'data'    => $postCardData['links'][0]['href'],
				'email'	  => $booking_details['email'],
				'booking_id' => $booking_details['booking_id'],
				'message' => $windCaveDetail['transactions'][0]['responseText']
			];
		}
		else
		{
			return [
				'code'    => 0,
				'status'  => 'error',
				'message' => 'Payment Failed. '.$windCaveDetail['transactions'][0]['responseText']
			];
		}
    }
	
	public function updateTransactionDetails()
	{
		$validator = Validator::make(
            $this->params,
            [
                'sessionID' => 'required'
            ]
        );
        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
		
		$booking_details = BookingDetails::where('session_id', $this->params['sessionID'])->orWhere('id', $reservationId)->first();
        if($windCaveDetail['transactions'][0]['responseText'] == 'APPROVED') {
            $paramTransactionReceipt = [
                'accountId'                          => $accountPropertyId,
                'amount'                             => $this->params['amount'],
                'cardId'                             => $windCaveDetail['transactions'][0]['id'],
                'dateOfTransaction'                  => Carbon::now(),
                'receiptType'                        => "CreditCard",
                'source'                             => "Standard",
                'useRmsAccountingDateForPostingDate' => "true",
            ];
            $api->transactionReceipt($paramTransactionReceipt);
        }	
	}
}
