<?php

/**
 * Database config
 * 
 * Please duplicate this file to use this config
 */

return [
    'default'     => 'db',
    'migrations'  => 'migrations',
    'connections' => [
        'db' => [
            'driver'    => env('DB_CONNECTION', 'mysql'),
            'host'      => env('DB_HOST', ''),
            'port'      => env('DB_PORT', ''),
            'database'  => env('DB_DATABASE', ''),
            'username'  => env('DB_USERNAME', ''),
            'password'  => env('DB_PASSWORD'),
            'unix_socket'  => env('DB_SOCKET', ''),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
            'strict'    => false,
            'options'   => [
                \PDO::ATTR_EMULATE_PREPARES => true
            ],
        ],

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
