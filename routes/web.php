<?php

/** @var \Laravel\Lumen\Routing\Router $router */

use Illuminate\Support\Facades\Redis;

$router->get('/', function () {
    $p = Redis::incr('p');
    return $p;
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
        $router->get('{id}', 'PropertyController@detail');
        $router->get('', 'PropertyController@list');
        $router->get('availability-grid', 'PropertyController@availabilityGrid');
    });
    $router->post('payment/{reservationId}', 'PaymentController@payment');
    $router->get('countries', 'CountryController@list');
    $router->group(['prefix' => 'rate'], function () use ($router) {
        $router->get('', 'RateController@rateList');
    });
});

