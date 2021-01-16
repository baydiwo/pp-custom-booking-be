<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class PropertyController {
    private $authToken;
    private $request;
    private $params;

    public function __construct(Request $request)
    {
        $this->authToken = Cache::get('authToken')['token'];
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