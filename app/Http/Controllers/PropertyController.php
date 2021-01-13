<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class PropertyController {
    private $authToken;
    private $request;
    private $params;

    public function __construct(Request $request)
    {
        // $this->authToken = Redis::get('authToken');
        $this->authToken = "b9fe163e302a5884d4e8486d860437b3";
        $this->request = $request;
        $this->params  = $request->all();
    }
    
    public function detail($id)
    {
        $endpoint = 'properties/'.$id.'?modelType=full';
        $response = Http::withHeaders([
            'authtoken' => $this->authToken
        ])->get(env('BASE_URL_RMS').$endpoint);
        return $response->json();
    }
}