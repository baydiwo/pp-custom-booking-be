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
		$response_get = json_encode($_GET);
		$response_post = json_encode($_POST);
        $model = new ModelWebhooks();
        $model->response = $response;
        $model->response_get = $response_get;
        $model->response_post = $response_post;
		$model->save();
		
        return [
            'code' => 1,
            'status' => 'success',
            'data' => $model
        ];
    }
}