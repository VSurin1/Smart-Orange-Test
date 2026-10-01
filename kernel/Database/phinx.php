<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$rootPath = dirname(__DIR__, 2);

Dotenv::createImmutable($rootPath)->load();

$config = require $rootPath . '/config/database.php';

$driver = $config['driver'];
$connection = $config['connections'][$driver];

return [
    'paths' => [
        'migrations' => $rootPath . '/database/migrations',
        'seeds' => $rootPath . '/database/seeds',
    ],

    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',

        'development' => [
            'adapter' => $driver,
            'host' => $connection['host'],
            'port' => $connection['port'],
            'name' => $connection['database'],
            'user' => $connection['username'],
            'pass' => $connection['password'],
            'charset' => $connection['charset'],
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],

    'version_order' => 'creation',
];