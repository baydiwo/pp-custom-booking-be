<?php

/**
 * Database config
 * 
 * Please duplicate this file to use this config
 */

return [
    'default'     => 'redis',
    'migrations'  => 'migrations',
    'connections' => [
    ],
    'redis' => [
        'client' => 'predis',
        'default' => [
            'host' => env('REDIS_HOST', 'localhost'),
            'password' => env('REDIS_PASSWORD', null),
            'port' => env('REDIS_PORT', 6379),
            'database' => 0,
        ],
    ],
];
