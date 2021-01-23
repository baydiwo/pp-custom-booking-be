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

    public function createGuest($param)
    {
        $endpoint = 'guests?ignoreMandatoryFieldWarnings=false';

        $response = Http::withHeaders([
            'authToken' => $this->authToken
        ])->post(env('BASE_URL_RMS') . $endpoint, $param);

        return $response->json();
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

    public function availabilityrategrid($params)
    {
        $value = Cache::remember(
            'availabilityrategrid'. json_encode($params),
            10 * 60, function () use ($params) {
            $endpoint = 'availabilityrategrid';

            $response = Http::withHeaders([
                'authToken' => $this->authToken
            ])->post(env('BASE_URL_RMS') . $endpoint, $params);

            return $response->json();
        });

        return $value
        ;
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
}
