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

class CountryController
{
    private $authToken;
    private $request;
    private $params;
    //comment
    public function __construct(Request $request)
    {
        $this->authToken = Cache::get('authToken')['token'];
        $this->request = $request;
        $this->params  = $request->all();
    }

    public function list()
    {
        $value = Cache::remember('countries', 10 * 60, function()
        {
            $endpoint = 'countries';
            $response = Http::withHeaders([
                'authtoken' => $this->authToken
            ])->get(env('BASE_URL_RMS') . $endpoint);
    
            if(isset($response['Message'])) {
                throw new Exception(ucwords($response['Message']));
            }
    
            return $response->json();
        });

        return [
            'code'   => 1,
            'status' => 'success',
            'data'   => $value
        ];
    }
}
