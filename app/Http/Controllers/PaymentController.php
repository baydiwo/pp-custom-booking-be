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
        $detailReservation = $api->detailReservation($reservationId);
        if (isset($detailReservation['Message'])) {
            throw new Exception('Data Reservation Not Found');
        }
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
                'cvc'               => 'required',
                'amount'            => 'required'
            ]
        );
        if ($validator->fails())
            throw new Exception(ucwords(implode(' | ', $validator->errors()->all())));
        
        $paramsCreatePurchaseSessions = [
            "type"                => "purchase",
            "amount"              => $this->params['amount'],
            "currency"            => env('CURRENCY'),
            "merchantReference"   => "Private Properties",
            "storeCard"           => true,
            "storeCardIndicator"  => "single",
            "callbackUrls" => [
                "approved" => "https://pp-booking-staging.netlify.app/success",
                "declined" => "https://pp-booking-staging.netlify.app/fail", 
                "cancelled" => "https://pp-booking-staging.netlify.app/cancel", 
            ],
            "notificationUrl" => "https://pp-booking-staging.netlify.app/success"
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
                'cvc2'              => $this->params['cvc'],
            ]
        ];

        //get account property guest
        $accountProperty = $api->guestAccountProperty($detailReservation['guestId']);
        if ((isset($accountProperty['Message'])) || (count($accountProperty) == 0)) {
            throw new Exception('Account Property Guest Not Found');
        }

        $accountPropertyId = $accountProperty[0]['id'];

        //do payment
        $postCardData = $api->windCavePostCardData($ajaxPostUrl, $paramPostCardData);
        $cardId = $postCardData['id'];
        $windCaveDetail = $api->windCaveTransactionDetail($cardId);
        if(isset($windCaveDetail['errors'])) {
            throw new Exception('Wind Cave Transaction Detail Not Found');
        }

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

        return [
            'code'    => 1,
            'status'  => 'success',
            'data'    => $postCardData['links'][0]['href'],
            'message' => "Data Has Been ". $windCaveDetail['transactions'][0]['responseText']
        ];
    }
}
