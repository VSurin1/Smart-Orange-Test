<?php

use App\Controllers\LeadImportController;
use Kernel\Routing\Router;

/** @var Router $router */

$router->get('/', [LeadImportController::class, 'index']);
$router->post('/api/imports/start', [LeadImportController::class, 'start']);
$router->post('/api/imports/chunk', [LeadImportController::class, 'chunk']);
$router->post('/api/imports/finish', [LeadImportController::class, 'finish']);
$router->post('/api/imports/process', [LeadImportController::class, 'process']);
