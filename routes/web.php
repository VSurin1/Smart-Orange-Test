<?php

use App\Controllers\ApplicationController;
use Kernel\Routing\Router;

/** @var Router $router */

$router->get('/', [ApplicationController::class, 'index']);
