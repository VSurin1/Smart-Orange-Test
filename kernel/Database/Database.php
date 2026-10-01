<?php

namespace Kernel\Database;

use InvalidArgumentException;
use Kernel\Database\Drivers\MysqlDriver;
use PDO;

class Database
{
    private array $config;

    private ?PDO $connection = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function connection(): PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $driverName = $this->config['driver'];

        $driver = match ($driverName) {
            'mysql' => new MysqlDriver(),
            default => throw new InvalidArgumentException(
                'Неизвестный драйвер: ' . $driverName
            ),
        };

        $connectionConfig = $this->config['connections'][$driverName]
            ?? throw new InvalidArgumentException(
                'Нет настроек подключения для: ' . $driverName
            );

        $this->connection = $driver->connect($connectionConfig);

        return $this->connection;
    }
}