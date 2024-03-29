<?php

namespace App\Http\Controllers;

use App\Http\Helpers\Constant;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RateController
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

    public function rateList()
    {
        $endpoint = 'rates';
        $response = Http::withHeaders([
            'authtoken' => $this->authToken
        ])->get(env('BASE_URL_RMS') . $endpoint);

        if(isset($response['Message'])) {
            throw new Exception(ucwords($response['Message']));
        }
        return [
            'code' => 1,
            'status' => 'success',
            'data' => $response->json()
        ];
    }
}
