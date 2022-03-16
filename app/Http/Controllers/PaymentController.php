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
use App\Models\SessionDetails;
use App\Models\ModelPaymentDetails;
use App\Models\ModelTiming;

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

    public function payment(Request $request, $reservationId)
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

		if(!$booking_details)
			throw new Exception(ucwords('Booking details not found!'));
		else
		{
			$cTime = time();
			$expDate =strtotime($booking_details->expiry_date);
			$diffTime = $expDate-$cTime;
			if($diffTime < 60)
			{
				$httpCode = 500;
				$data = [
					'code' => 0,
					'status' => 'failed',
					'message' => 'Session Expired. Please Try Again!'
				];
				return response()->json($data, $httpCode);	
			}
				
			$now = Carbon::now();
			$from_date = Carbon::parse($booking_details['arrival_date']);
			$diffWeek = $now->diffIndays($from_date);
			
			if($diffWeek > 20)
				$amount        = number_format((0.3* $booking_details['accomodation_fee']) * 1.012, 2,'.','');
			else
				$amount        = number_format($booking_details['accomodation_fee'] * 1.012, 2,'.','');
		}
		
		//Start - Active Campaign API
		if($this->params['newsletterFlag'] == 'true')
		{
			$paramAcData = [
											"contact" => [
																		"email" => $booking_details['email'],
																		"firstName" => $booking_details['given'],
																		"lastName" => $booking_details['surname'],
																		"phone" => $booking_details['phone']
																	]
									];
			$endpointAc = 'https://privateproperties.api-us1.com/api/3/contacts';
			$responseAc = Http::withHeaders([
				'Api-Token' => 'f7f7f7d0870e23659fb522b332627d277e00d32114b891b5c8788e9d3009700b0de72322'
			])->post($endpointAc, $paramAcData);
		}
		// End - Active Campaign API

		$booking_id = $booking_details['booking_id'];
		
        //get account property based on Booking ID
		$reservationDetails = $api->getReservationDetails($booking_id);
		if(isset($reservationDetails['Message'])) {
			throw new Exception(ucwords($reservationDetails['Message']));
		}
		$accountPropertyId = $reservationDetails['accountId'];
		
        $paramsCreatePurchaseSessions = [
            "type"                => "purchase",
            "amount"              => $amount,
            "currency"            => env('CURRENCY'),
            "merchantReference"   => "Acc No: ".$accountPropertyId,
            "storeCard"           => true,
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
		
		if(env('BASE_URL_WINDCAVE') == 'https://sec.windcave.com/api/v1/')
			$ajaxPostUrl = $createPurchaseSessions['links'][2]['href'];	
		else
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

        //do payment
        $postCardData = $api->windCavePostCardData($ajaxPostUrl, $paramPostCardData);
		
		if(isset($postCardData['links'][0]['rel']) && $postCardData['links'][0]['rel'] == '3DSecure')
		{
			$payment_record = new ModelPaymentDetails();
			$payment_record->session_id = $postCardData['id'];
			$payment_record->account_id = $accountPropertyId;
			$payment_record->amount 	= $amount;
			$payment_record->booking_details_id = $reservationId;
			$payment_record->card_name = $this->params['cardHolderName'];
			$payment_record->card_number = substr($this->params['cardNumber'], -4);
			$payment_record->card_expmonth = $this->params['dateExpiryMonth'];
			$payment_record->card_expyear = $this->params['dateExpiryYear'];
			$payment_record->card_type = $this->params['cardType'];
			$payment_record->booking_id = $booking_id;
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
			$windCaveTxn = $api->windCavePaymentToken($windCaveDetail['transactions'][0]['id']);
			
			if(isset($windcaveTxn['card']['id'])){
				$payment_token = $windcaveTxn['card']['id'];
			}
			else
			{
				$payment_token = 0;
			}
			$payment_record = new ModelPaymentDetails();
			$payment_record->session_id = $postCardData['id'];
			$payment_record->account_id = $accountPropertyId;
			$payment_record->amount 	= $amount;
			$payment_record->booking_details_id = $reservationId;
			$payment_record->booking_id = $booking_id;
			$payment_record->txn_refno = $windCaveDetail['transactions'][0]['id'];
			$payment_record->card_name = $this->params['cardHolderName'];
			$payment_record->card_number = substr($this->params['cardNumber'], -4);
			$payment_record->card_expmonth = $this->params['dateExpiryMonth'];
			$payment_record->card_expyear = $this->params['dateExpiryYear'];
			$payment_record->card_type = $this->params['cardType'];
			$payment_record->payment_token = $payment_token;
			$payment_record->payment_status = '1';
			$payment_record->save();
			
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
			$payment_record->booking_id = $booking_id;
			$payment_record->card_name = $this->params['cardHolderName'];
			$payment_record->card_number = substr($this->params['cardNumber'], -4);
			$payment_record->card_expmonth = $this->params['dateExpiryMonth'];
			$payment_record->card_expyear = $this->params['dateExpiryYear'];
			$payment_record->card_type = $this->params['cardType'];
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
			$api = new ApiController($this->authToken, $this->request);
			
			//Fetching Payment token from Windcave and update it in Database
			$windCaveTxn = $api->windCavePaymentToken($payment_details['txn_refno']);
			$payment_token = (isset($windCaveTxn['card']['id'])) ? $windCaveTxn['card']['id'] : 0;
			
			$payment_details->payment_token = $payment_token;
			$payment_details->save();

			if($payment_details['txn_receipt_updated'] == '0')
			{				
				$payment_details->txn_receipt_updated = '1';
				$payment_details->save();
				
				$modelTiming = ModelTiming::where('booking_id', $payment_details['booking_id'])->where('process_type', 'Pencil')->first();
				
			
				$booking_details = BookingDetails::select('*')->where('id', $payment_details['booking_details_id'])->first();
				
				$status_update = $api->reservationStatus($payment_details['booking_id'], ['status' => 'Unconfirmed']);
				if($status_update)
				{
					if($modelTiming)
					{
						$modelTiming->unconfirmed_status = Carbon::now();
						$modelTiming->save();
					}
					$booking_details->booking_status = '1';
					$booking_details->save();
					$update_expiry = SessionDetails::where('booking_id', $payment_details['booking_id'])->where('status', '0')->first();
					if($update_expiry)
					{
						$update_expiry->status = '1';
						$update_expiry->save();
					}
				}
				
				$paramBookingDetails = [
								"id"			=> $payment_details['booking_id'],
								"accountId"		=> $payment_details['account_id'],
								"adults"		=> $booking_details['adults'],
								"areaId"		=> $booking_details['area_id'],
								"arrivalDate"	=> $booking_details['arrival_date'],
								"baseRateOverride"	=> 0,
								"bookingSourceId" => $booking_details['booking_source_id'],
								"categoryId"	=> $booking_details['category_id'],
								"children"		=> $booking_details['children'],
								"departureDate" => $booking_details['departure_date'],
								"guestId"		=> $booking_details['guest_id'],
								"infants"		=> $booking_details['infants'],
								"notes"			=> $booking_details['notes'],
								"rateTypeId"	=> $booking_details['rate_type_id'],
								"resTypeId"		=> 0,
								"status"		=> "Unconfirmed",
								"travelAgentId"	=> 8
							];
	
				$bookingResult = $api->reservationUpdate($paramBookingDetails);
				if($bookingResult && $modelTiming)
				{
					$modelTiming->reservation_update = Carbon::now();
					$modelTiming->save();
				}
				
				$paramGuestToken = [
										"cardHolderName"		=> $payment_details['card_name'],
										"cardType"				=> str_replace(' ','',$payment_details['card_type']),
										"description"			=> "Customers credit card",
										"expiryDate"			=>$payment_details['card_expmonth'].'/'.$payment_details['card_expyear'],
										"lastFourDigitsOfCard"	=> $payment_details['card_number'],
										"token"					=> $payment_token
									];			
				
				$gtResult = $api->guestToken($booking_details['guest_id'], $paramGuestToken);
				
				$now = Carbon::now();
				$from_date = Carbon::parse($booking_details['arrival_date']);
				$diffWeek = $now->diffIndays($from_date);
				if($modelTiming)
				{
					$modelTiming->guest_token = Carbon::now();
					$modelTiming->save();
				}

				
				if($diffWeek > 20)
					$cc_fee = number_format((0.3* $booking_details['accomodation_fee']) * 0.012, 2);
				else
					$cc_fee = number_format($booking_details['accomodation_fee'] * 0.012, 2);
				
				$paramSundries = [
									[
										'accountId'                          => $payment_details['account_id'],
										'amount'                             => $cc_fee,
										'comment'							 => 'Credit Card Transaction Fee.',
										'dateOfTransaction'                  => Carbon::now(),
										'description'						 => 'Credit card Fee for Booking - '.$payment_details['booking_id'],
										'source'                             => "Standard",
										'sundryId'							 =>	7,
										'useRmsAccountingDateForPostingDate' => "true",
										'useSecondaryCurrency'				 => 'useDefault'
									]
								];
				
				$sundriesResult = $api->addSundries($paramSundries);
				
				//Check for card type and set cardID
				$card_type = strtolower($payment_details['card_type']);
				$mc_cards = ['master', 'mastercard', 'master card'];
				if( in_array($card_type,$mc_cards))
					$card_id = 2;
				else
					$card_id = 3;
				
				// Start - Add Transaction Receipt
				$paramTransactionReceipt = [
												'accountId'                          => $payment_details['account_id'],
												'amount'                             => $payment_details['amount'],
												'cardId'                             => $card_id,
												'dateOfTransaction'                  => Carbon::now(),
												'receiptType'                        => "CreditCard",
												'source'                             => "Standard",
												'useRmsAccountingDateForPostingDate' => "true",
												'transactionReference'				 => $payment_details['txn_refno'],
												'comment'							 => 'Property Booking Payment',
												'description'						 => 'Payment for Booking - '.$payment_details['booking_id'],
												'token'								 => $payment_token,
												'useSecondaryCurrency'				 => 'useDefault'
											];
	
				$result = $api->transactionReceipt($paramTransactionReceipt);
				if($result && $modelTiming)
				{
					$modelTiming->txn_receipt = Carbon::now();
					$modelTiming->save();
				}
				
				// End - Add Transaction Receipt
				
				$paramRequirement = [
										"amount"		=> 0,//$booking_details['pet_fee'],
										"dateFrom"		=> $booking_details['arrival_date'],
										"dateTo"		=>$booking_details['departure_date'],
										"quantity"		=>$booking_details['pets'],
										"requirementId" => 8
									];
				
				$reqResult = $api->reservationRequirement($payment_details['booking_id'], $paramRequirement);
				
				$result = $api->reservationStatus($payment_details['booking_id'], ['status' => 'Confirmed']);
				if($result)
				{
					if($modelTiming)
					{
						$modelTiming->confirmed_status = Carbon::now();
						$modelTiming->save();
					}
					
					$booking_details->booking_status = '2';
					$booking_details->save();
					$payment_details->rms_updated = '1';
					$payment_details->save();
				}
			}
			return $payment_details['booking_id'];
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
			$booking_details_data = BookingDetails::select('email','booking_id')->where('id', $booking_details_id)->first();
			
			/*$ctime = Carbon::now();
			$modelTiming = new ModelTiming();
			$modelTiming->token = 'Payment Success - Booking details Updation Initiated';
			$modelTiming->rate_start = $ctime;
			$modelTiming->rate_end = $ctime;
			$modelTiming->pencil_start = $ctime;
			$modelTiming->pencil_end = $ctime;
			$modelTiming->booking_id = $booking_details_data['booking_id'];
			$modelTiming->process_type = 'PaymentSuccess';
			$modelTiming->status = '1';
			$modelTiming->save();*/
			
			$booking_ref_id = $this->updateTransactionDetails($request['sessionId']);
			
			$booking_details = BookingDetails::select('email','booking_id')->where('id', $booking_details_id)->first();
			return redirect(env('BOOKING_URL').'/#/thank-you/'.$booking_details['booking_id'].'/'.$booking_details['email']);
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
		return redirect(env('BOOKING_URL').'/#/payment/'.$txn_details['booking_details_id'].'?stat=2');
	}
	
	public function paymentFailed(Request $request)
	{
		$txn_details = ModelPaymentDetails::where('session_id', $request['sessionId'])->first();
		$txn_details->payment_status = '3';
		$txn_details->save();
		return redirect(env('BOOKING_URL').'/#/payment/'.$txn_details['booking_details_id'].'?stat=3');
	}
}