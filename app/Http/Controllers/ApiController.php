<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Http;

class ApiController {
    
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
        $response = Http::post(env('BASE_URL_RMS').$endpoint, $param);
        return $response->json();
    }

    public function createGuest($token, $param)
    {
        $endpoint = 'guests?ignoreMandatoryFieldWarnings=false';
        
        $response = Http::withHeaders([
            'authtoken' => $token
        ])->post(env('BASE_URL_RMS').$endpoint, $param);

        return $response->json();
    }

    public function detailProperty($id, $token) 
    {
        $endpoint = 'properties/'.$id.'?modelType=full';
        $response = Http::withHeaders([
            'authtoken' => $token
        ])->get(env('BASE_URL_RMS').$endpoint);
        
        return $response->json();
    }

    public function detailPropertySetting($id, $token) 
    {
        $endpoint = 'properties/'.$id.'/ibe/settings';
        $response = Http::withHeaders([
            'authtoken' => $token
        ])->get(env('BASE_URL_RMS').$endpoint);
        
        return $response->json();
    }
}