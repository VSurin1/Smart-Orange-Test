<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Application;

readonly class ApplicationService
{
    public function __construct(
        private Application $application
    ) {}

    public function all(): array
    {
        return $this->application->all();
    }
}
