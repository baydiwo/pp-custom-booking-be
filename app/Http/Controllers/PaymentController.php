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
        $validator = Validator::make(
            $this->params,
            [
                'cardHolderName' 	=> 'required',
                'cardNumber'        => 'required',
                'dateExpiryMonth'   => 'required',
                'dateExpiryYear'    => 'required',
                'cvc'               => 'required'
            ]
        );
        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
        
        $api = new ApiController($this->authToken, $this->request);
		
		$booking_details = BookingDetails::where('id', $reservationId)->first();
		
		$paramMinNight = [
            'categoryIds' => [$booking_details['category_id']],
            'dateFrom'    => $booking_details['arrival_date'],
            'dateTo'      => $booking_details['departure_date'],
            'propertyId'  => 1,
            'rateIds'     => [$booking_details['rate_type_id']]
        ];
		
        $minNight = $api->availabilityrategrid($paramMinNight);
		
        if (!$minNight) {
            throw new Exception(ucwords('Booking not available for the selected dates!'));//Minimum Night Not Found'));
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
                "approved" => env('API_URL')."/transaction/success",
                "declined" => env('API_URL')."/transaction/fail", 
                "cancelled" => env('API_URL')."/transaction/cancel"
            ], 
            "notificationUrl" => env('API_URL')."/success"
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
		print_r($createPurchaseSessions);
		die;
		
        $ajaxPostUrl = $createPurchaseSessions['links'][2]['href'];
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
            throw new Exception('Account Property of Guest Not Found');
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
			$payment_record->booking_details_id = $reservationId;
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
			$payment_record->booking_details_id = $reservationId;
			$payment_record->payment_status = '1';
			$payment_record->save();
			
			//$booking_id = $this->updateTransactionDetails($postCardData['id']);
			$booking_id = 1;
			
			return [
				'code'    => 1,
				'status'  => 'success',
				'data'    => $postCardData['links'][0]['href'],
				'email'	  => $booking_details['email'],
				'booking_id' => $booking_id,
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
	
	private function updateTransactionDetails($sessionID)
	{
		$payment_details = ModelPaymentDetails::where('session_id', $sessionID)->first();
        if($payment_details) {
			
			$booking_details = BookingDetails::where('id', $payment_details['booking_details_id'])->first();
			$paramDetails = [
								'arrivalDate'   => $booking_details->dateFrom,
								'departureDate' => $booking_details->dateTo,
								'surname'		=> $booking_details->surname,
								'given'         => $booking_details->given,
								'email'         => $booking_details->email,
								'adults'        => $booking_details->adults,
								'areaId'       	=> $booking_details->areaId,
								'categoryId'   	=> $booking_details->categoryId,
								'children'      => $booking_details->children,
								'infants'       => $booking_details->infants,
								'notes'      	=> $booking_details->notes,
								'address'       => $booking_details->address,
								'rateTypeId'  	=> $booking_details->rateTypeId,
								'state'         => $booking_details->state,
								'town'          => $booking_details->town,
								'countryId'    	=> $booking_details->countryId,
								'nights'        => $booking_details->nights,
								'phone'         => $booking_details->phone,
								'postCode'     	=> $booking_details->postCode,
								'pets'      	=> (isset($booking_details->pets) && $booking_details->pets != '') ? $booking_details->pets : 0,
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
			
			$booking_id = (isset($response['id']) && $response['id'] != '') ? $response['id'] : 0;
			$booking_details->booking_id = $booking_id;
			$booking_details->save();
			
            $paramTransactionReceipt = [
                'accountId'                          => $payment_details['account_id'],
                'amount'                             => $payment_details['amount'],
                'cardId'                             => $payment_details['session_id'],
                'dateOfTransaction'                  => Carbon::now(),
                'receiptType'                        => "CreditCard",
                'source'                             => "Standard",
                'useRmsAccountingDateForPostingDate' => "true",
            ];
            $result = $api->transactionReceipt($paramTransactionReceipt);
			return $booking_id;
        }	
	}
	
	public function paymentSuccess(Request $request)
	{
        $session_id = (isset($request['sessionId']) && $request['sessionId'] != '') ? $request['sessionId'] : 0;
		
		$txn_details = ModelPaymentDetails::where('session_id', $request['sessionId'])->first();
		if($txn_details)
		{
			$booking_details_id = $txn_details['booking_details_id'];
			
			$txn_details->payment_status = '1';
			$txn_details->save();
			//$booking_id = $this->updateTransactionDetails($request['sessionId']);
			$booking_id = 1;
			
			$booking_details = BookingDetails::select('email')->where('id', $booking_details_id)->first();
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
		return redirect(env('BOOKING_URL').'/#/payment/'.$booking_id.'?stat=2');
	}
	
	public function paymentFailed(Request $request)
	{
		$txn_details = ModelPaymentDetails::where('session_id', $request['sessionId'])->first();
		$txn_details->payment_status = '3';
		$txn_details->save();
		return redirect(env('BOOKING_URL').'/#/payment/'.$booking_id.'?stat=3');
	}
}