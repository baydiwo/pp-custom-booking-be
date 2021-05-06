<?php

namespace App\Http\Controllers;

use App\Models\ModelWebhooks;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use phpDocumentor\Reflection\Types\This;

class WebhookController
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

    public function getResponse(Request $request)
    {
		$response = json_encode($request);
        $model = new ModelWebhooks();
        $model->response = $response;
		$model->save();
		
        return [
            'code' => 1,
            'status' => 'success',
            'data' => $data
        ];
    }
}