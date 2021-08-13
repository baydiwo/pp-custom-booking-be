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
use App\Models\ModelPaymentDetails;

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
		
		$booking_details = BookingDetails::where('booking_id', $reservationId)->orWhere('id', $reservationId)->first();
		/*$paramDetails = [
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
                'cardNumber'        => 'required',
                'dateExpiryMonth'   => 'required',
                'dateExpiryYear'    => 'required',
                'cvc'               => 'required'
            ]
        );
        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
        
		
		$paramMinNight = [
            'categoryIds' => [$booking_details['category_id']],
            'dateFrom'    => $booking_details['arrival_date'],
            'dateTo'      => $booking_details['departure_date'],
            'propertyId'  => 1,
            'rateIds'     => [$booking_details['rate_type_id']]
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
                "approved" => "https://pp-booking-apidev.herokuapp.com/transaction/success",
                "declined" => "https://pp-booking-apidev.herokuapp.com/transaction/fail", 
                "cancelled" => "https://pp-booking-apidev.herokuapp.com/transaction/cancel"
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
        $accountProperty = $api->guestAccountProperty($booking_details->guest_id);//$detailReservation['guestId']);
        if ((isset($accountProperty['Message'])) || (count($accountProperty) == 0)) {
            throw new Exception('Account Property Guest Not Found');
        }
		
        $accountPropertyId = $accountProperty[0]['id'];

        //do payment
        $postCardData = $api->windCavePostCardData($ajaxPostUrl, $paramPostCardData);
		
		if(isset($postCardData['links'][0]['rel']) && $postCardData['links'][0]['rel'] == '3DSecure')
		{
			$payment_record = new ModelPaymentDetails();
			$payment_record->session_id = $postCardData['id'];
			$payment_record->account_id = $accountPropertyId;
			$payment_record->amount 	= $amount;
			$payment_record->booking_id = $reservationId;
			$payment_record->save();
			
			return [
				'code'    => 1,
				'status'  => 'success',
				'data'    => $postCardData['links'][0]['href'],
				'message' => "Waiting for 3D Secure Code verification"
			];
		}
		
		if(!isset($postCardData['id'])) {
            throw new Exception('Wind Cave Transaction Detail Not Found');
        }
		
        $cardId = $postCardData['id'];
        $windCaveDetail = $api->windCaveTransactionDetail($cardId);
        if(isset($windCaveDetail['errors'])) {
            throw new Exception('Wind Cave Transaction Detail Not Found');
        }
		
		if($windCaveDetail['transactions'][0]['responseText'] == 'APPROVED') {
			$payment_record = new ModelPaymentDetails();
			$payment_record->session_id = $postCardData['id'];
			$payment_record->account_id = $accountPropertyId;
			$payment_record->amount 	= $amount;
			$payment_record->booking_id = $reservationId;
			$payment_record->payment_status = '1';
			$payment_record->save();
			
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
			$payment_record = new ModelPaymentDetails();
			$payment_record->session_id = $postCardData['id'];
			$payment_record->account_id = $accountPropertyId;
			$payment_record->amount = $amount;
			$payment_record->payment_status = '3';
			$payment_record->save();
			return [
				'code'    => 0,
				'status'  => 'error',
				'message' => $windCaveDetail['transactions'][0]['responseText']
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
	
	public function paymentSuccess(Request $request)
	{
        $session_id = (isset($request['sessionId']) && $request['sessionId'] != '') ? $request['sessionId'] : 0;
		
		$txn_details = ModelPaymentDetails::where('session_id', $request['sessionId'])->first();
		if($txn_details)
		{
			$booking_id = $txn_details['booking_id'];
			
			$txn_details->payment_status = '1';
			$txn_details->save();
			
			$booking_details = BookingDetails::select('email')->where('booking_id', $booking_id)->orWhere('id', $booking_id)->first();
			return redirect(env('BOOKING_URL').'/#/thank-you/'.$booking_id.'/'.$booking_details['email']);
		}
		else
		{
			return redirect('https://privateproperties.com.au');
		}
	}
	
	public function paymentCancelled(Request $request)
	{
		$txn_details = ModelPaymentDetails::where('session_id', $request['sessionId'])->first();
		$txn_details->payment_status = '2';
		$txn_details->save();
		return redirect(env('BOOKING_URL').'/#/payment/'.$booking_id);
	}
	
	public function paymentFailed(Request $request)
	{
		$txn_details = ModelPaymentDetails::where('session_id', $request['sessionId'])->first();
		$txn_details->payment_status = '3';
		$txn_details->save();
		return redirect(env('BOOKING_URL').'/#/payment/'.$booking_id);
	}
}