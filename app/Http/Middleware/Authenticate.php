<?php

namespace App\Http\Middleware;

use App\Http\Controllers\ApiController;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class Authenticate
{
    /**
     * The authentication guard factory instance.
     *
     * @var \Illuminate\Contracts\Auth\Factory
     */
    protected $auth;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Contracts\Auth\Factory  $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        $api = new ApiController(NULL, $request);
        $authToken = Cache::get('authToken');
        if($authToken == NULL) {
            $dataToken = $api->authToken();
            Cache::forever('authToken', $dataToken);
        } else {
            if($authToken['expiryDate'] < Carbon::now()) {
                $dataToken = $api->authToken();
                Cache::forever('authToken', $dataToken);
            }
        }

        // $redis = Redis::connection();

        // if(!$redis->get('authToken')) {
        //     $api = new ApiController();
        //     $auth = $api->authToken(); 

        //     $redis->set('authToken', $auth['token']);
        //     $redis->set('expiryDateToken', $auth['expiryDate']);
        // } else {
        //     if($redis->get('expiryDateToken') < Carbon::now()) {
        //         $api = new ApiController();
        //         $auth = $api->authToken(); 
    
        //         $redis->set('authToken', $auth['token']);
        //         $redis->set('expiryDateToken', $auth['expiryDate']);
    
        //     }
        // }

        $response = $next($request);

        $response->headers->set('Access-Control-Allow-Methods', 'POST, GET, OPTIONS, PUT, DELETE');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With, Application','ip');

        return $response;
    }
}
