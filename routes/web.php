<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/
use Illuminate\Support\Facades\Redis;

$router->get('/', function () {
    $p = Redis::incr('p');
    return $p;
});
$router->get('fail/', function () {
    return 'failed';
});
$router->get('success/', function () {
    return 'Success';
});
$router->get('cancel/', function () {
    return 'Cancelled';
});

$router->get('users_with_query', "UserController@getUser");
$router->get('users_with_cache', "UserController@index");
$router->get('testing', "UserController@test");
$router->post('auth-token', "ApiController@authToken");

$router->group(['middleware' => 'auth.token'], function () use ($router) {
    $router->group(['prefix' => 'booking'], function () use ($router) {
        $router->get('{id}', 'BookingController@detail');
        $router->post('', 'BookingController@create');
    });
	
    $router->group(['prefix' => 'property'], function () use ($router) {
        $router->get('', 'PropertyController@list');
        $router->get('availability-grid', 'PropertyController@availabilityGrid');
        $router->get('availability-grid-test-concurrent', 'PropertyController@availabilityGridTestConcurrent');
        $router->get('availability-grid-concurrent', 'PropertyController@availabilityGridConcurrent');
        $router->get('check-availability', 'PropertyController@checkAvailability');
        $router->get('check-availability-concurrent', 'PropertyController@checkAvailabilityConcurrent');
        $router->get('check-availability-concurrent-new', 'PropertyController@checkAvailabilityConcurrentNew');
        $router->get('area-by-year', 'PropertyController@areaByYear');
		$router->post('get-availability-area', 'PropertyController@getAvailabilityDates');
		$router->get('get-availability-dates', 'PropertyController@getAvailabilityDatesByArea');
		$router->get('get-availability-dates-arrival', 'PropertyController@getAvailabilityDatesADByArea');
		$router->get('get-availability-areas', 'PropertyController@getAvailabilityAreasByDate');
        $router->get('{id}', 'PropertyController@detail');
    });

    $router->group(['prefix' => 'webhooks'], function () use ($router) {
        $router->get('', 'WebhookController@getResponse');
        $router->post('', 'WebhookController@getResponse');
    });
	
    $router->group(['prefix' => 'property-details'], function () use ($router) {
        $router->get('{id}', 'PropertyController@propertyAreaDetail');
    });
	
    $router->post('payment/{reservationId}', 'PaymentController@payment');
    $router->get('countries', 'CountryController@list');
    $router->group(['prefix' => 'rate'], function () use ($router) {
        $router->get('', 'RateController@rateList');
    });
});

