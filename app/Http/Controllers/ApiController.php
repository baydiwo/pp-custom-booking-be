<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ApiController
{
    private $authToken;
    private $request;
    private $params;

    public function __construct($authToken = NULL, Request $request)
    {
        $this->authToken = $authToken;
        $this->request = $request;
        $this->params  = $request->all();
    }

    public function authToken()
    {
        $endpoint = 'authToken';
        $param = [
            'agentId'             => env('AGENT_ID'),
            'agentPassword'       => env('AGENT_PASSWORD'),
            'clientId'            => env('CLIENT_ID'),
            'clientPassword'      => env('CLIENT_PASSWORD'),
            'useTrainingDatabase' => env('USE_TRAINING_DATABASE'),
            'moduleType' => [
                'Distribution'
            ]
        ];
        $response = Http::post(env('BASE_URL_RMS') . $endpoint, $param);
        return $response->json();
    }

    public function detailReservation($id)
    {
        $endpoint = 'reservations/'.$id;

        $response = Http::withHeaders([
            'authToken' => $this->authToken
        ])->get(env('BASE_URL_RMS') . $endpoint);

        return $response->json();
    }

    public function detailArea($id)
    {
        $endpoint = 'areas/'.$id;

        $response = Http::withHeaders([
            'authToken' => $this->authToken
        ])->get(env('BASE_URL_RMS') . $endpoint);

        return $response->json();
    }

    public function createGuest($param)
    {
        $endpoint = 'guests?ignoreMandatoryFieldWarnings=false';

        $response = Http::withHeaders([
            'authToken' => $this->authToken
        ])->post(env('BASE_URL_RMS') . $endpoint, $param);

        return $response->json();
    }

    public function guestAccountProperty($id)
    {
        $endpoint = 'guests/'.$id.'/accounts';

        $response = Http::withHeaders([
            'authToken' => $this->authToken
        ])->get(env('BASE_URL_RMS') . $endpoint);

        return $response->json();
    }

    public function guestDetail($id)
    {
        $endpoint = 'guests/'.$id;

        $response = Http::withHeaders([
            'authToken' => $this->authToken
        ])->get(env('BASE_URL_RMS') . $endpoint);

        return $response->json();
    }

    public function guestSearch($param)
    {
        $endpoint = 'guests/search';

        $response = Http::withHeaders([
            'authToken' => $this->authToken
        ])->post(env('BASE_URL_RMS') . $endpoint, $param);

        return $response->json();
    }

    public function guestToken($id, $param)
    {
		$endpoint = 'guests/' . $id . '/token';
        $response = Http::withHeaders([
            'authToken' => $this->authToken
        ])->put(env('BASE_URL_RMS') . $endpoint, $param);

        return $response->json();
    }

    public function transactionReceipt($param)
    {
        $endpoint = 'transactions/receipt';

        $response = Http::withHeaders([
            'authToken' => $this->authToken
        ])->post(env('BASE_URL_RMS') . $endpoint, $param);

        return $response->json();
    }

    public function getReservationDetails($id)
    {
        $value = Cache::remember('reservation_details_' . $id, 10 * 60, function () use ($id) {
            $endpoint = 'reservations/' . $id . '?modelType=basic';
            $response = Http::withHeaders([
                'authToken' => $this->authToken
            ])->get(env('BASE_URL_RMS') . $endpoint);

            return $response->json();
        });

        return $value;
    }

    public function detailProperty($id)
    {
        $value = Cache::remember('property_' . $id, 10 * 60, function () use ($id) {
            $endpoint = 'properties/' . $id . '?modelType=basic';
            $response = Http::withHeaders([
                'authToken' => $this->authToken
            ])->get(env('BASE_URL_RMS') . $endpoint);

            return $response->json();
        });

        return $value;
    }

    public function listProperty()
    {
        $value = Cache::remember('list_property', 10 * 60, function () {
            $endpoint = 'properties/';
            $response = Http::withHeaders([
                'authToken' => $this->authToken
            ])->get(env('BASE_URL_RMS') . $endpoint);

            return $response->json();
        });

        return $value;
    }

    public function detailPropertySetting($id)
    {
        $value = Cache::remember('property_setting_' . $id, 10 * 60, function () use ($id) {
            $endpoint = 'properties/' . $id . '/ibe/settings';
            $response = Http::withHeaders([
                'authToken' => $this->authToken
            ])->get(env('BASE_URL_RMS') . $endpoint);

            return $response->json();
        });

        return $value;
    }

    public function detailCategory($id)
    {
        $value = Cache::remember('category_' . $id, 10 * 60, function () use ($id) {
            $endpoint = 'categories/' . $id;
            $response = Http::withHeaders([
                'authToken' => $this->authToken
            ])->get(env('BASE_URL_RMS') . $endpoint);

            return $response->json();
        });

        return $value;
    }

    public function areaConfiguration($id)
    {
        $value = Cache::remember('category_areas_' . $id, 10 * 60, function () use ($id) {
            $endpoint = 'areas/' . $id . '/configuration';
            $response = Http::withHeaders([
                'authToken' => $this->authToken
            ])->get(env('BASE_URL_RMS') . $endpoint);

            return $response->json();
        });

        return $value;
    }

    public function reservationStatus($id, $param)
    {
		$endpoint = 'reservations/' . $id . '/status';
        $response = Http::withHeaders([
            'authToken' => $this->authToken
        ])->put(env('BASE_URL_RMS') . $endpoint, $param);

        return $response->json();
    }

    public function listArea($propertyId)
    {
        $value = Cache::remember('list_areas_' . $propertyId, 10 * 60, function () use ($propertyId) {
            $endpoint = 'areas?propertyId=' . $propertyId . '&limit=300';
            $response = Http::withHeaders([
                'authToken' => $this->authToken
            ])->get(env('BASE_URL_RMS') . $endpoint);

            return $response->json();
        });

        return $value;
    }

    public function rateQuote($params)
    {
        $value = Cache::remember(
            'rateQuote_'. json_encode($params),
            10 * 60, function () use ($params) {
            $endpoint = 'rates/rateQuote';

            $response = Http::withHeaders([
                'authToken' => $this->authToken
            ])->post(env('BASE_URL_RMS') . $endpoint, $params);

            return $response->json();
        });

        return $value;
    }

    public function listRates()
    {
        $value = Cache::remember(
            'rates',
            10 * 60, function () {
            $endpoint = 'rates';

            $response = Http::withHeaders([
                'authToken' => $this->authToken
            ])->get(env('BASE_URL_RMS') . $endpoint);

            return $response->json();
        });

        return $value;
    }

    public function availabilityrategrid($params)
    {
        $endpoint = 'availabilityRateGrid';

        $response = Http::withHeaders([
            'authToken' => $this->authToken
        ])->post(env('BASE_URL_RMS') . $endpoint, $params);

           
        return $response->json();
    }

    public function windCaveCreatePurchaseSessions($params)
    {
        $endpoint = 'sessions';
        $auth = base64_encode(env('WINDCAVE_USERNAME').':'.env('WINDCAVE_API_KEY'));
        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$auth
        ])->post(env('BASE_URL_WINDCAVE') . $endpoint, $params);

        return $response->json();
    }

    public function windCavePostCardData($url, $params)
    {
        $response = Http::post($url, $params);
        return $response->json();
    }
    
    public function windCaveTransactionDetail($cardId)
    {
        $endpoint = 'sessions/'.$cardId;
        $auth = base64_encode(env('WINDCAVE_USERNAME').':'.env('WINDCAVE_API_KEY'));
        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$auth
        ])->get(env('BASE_URL_WINDCAVE') . $endpoint);

        return $response->json();
    }
    
    public function windCavePaymentToken($txnid)
    {
        $endpoint = 'transactions/'.$txnid;
        $auth = base64_encode(env('WINDCAVE_USERNAME').':'.env('WINDCAVE_API_KEY'));
        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$auth
        ])->get(env('BASE_URL_WINDCAVE') . $endpoint);

        return $response->json();
    }
}
