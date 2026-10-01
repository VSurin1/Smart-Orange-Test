<?php

declare(strict_types=1);

namespace App\Models;

use PDO;

class Application
{
    public function __construct(
        private PDO $pdo
    ) {}

    public function all(): array
    {
        return $this->pdo
            ->query('SELECT * FROM applications ORDER BY id DESC')
            ->fetchAll();
    }
}
