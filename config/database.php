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
       /* 'db' => [
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
        ],*/
		
		 'db' => [
			'driver' => 'mysql',
			'host' => env('DB_HOST', ''),
			'port' => env('DB_PORT', ''),
			'database' => env('DB_DATABASE', ''),
			'username' => env('DB_USERNAME', ''),
			'password' => env('DB_PASSWORD', ''),
			'unix_socket' => env('DB_SOCKET', ''),
			'charset' => 'utf8mb4',
			'collation' => 'utf8mb4_unicode_ci',
			'prefix' => '',
			'sslmode' => env('DB_SSLMODE', 'prefer'),
			'options'   => array(
				PDO::MYSQL_ATTR_SSL_CERT    => env('MYSQL_ATTR_SSL_CA', ''),
			),
			'strict' => true,
			'engine' => null,
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
