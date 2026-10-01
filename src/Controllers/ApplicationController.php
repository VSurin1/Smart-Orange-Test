<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ApplicationService;

readonly class ApplicationController
{
    public function __construct(
        private ApplicationService $applicationService
    ) {}

    public function index()
    {
        $applications = $this->applicationService->all();

        echo '<pre>';
        print_r($applications);
        die;
    }
}
