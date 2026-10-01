<?php

declare(strict_types=1);

use App\Controllers\ApplicationController;
use App\Models\Application;
use App\Services\ApplicationService;
use Dotenv\Dotenv;
use Kernel\Database\Database;
use Kernel\Routing\Router;

require dirname(__DIR__) . '/vendor/autoload.php';

$rootPath = dirname(__DIR__);
$method = $_SERVER['REQUEST_METHOD'];

try {
    Dotenv::createImmutable($rootPath)->load();

    $config = require $rootPath . '/config/database.php';

    $database = new Database($config);

    // Создаём контроллер только при совпадении маршрута.
    $resolveController = static function (string $class) use ($database): object {
        return match ($class) {
            ApplicationController::class => new ApplicationController(
                new ApplicationService(
                    new Application($database->connection())
                )
            ),
            default => throw new RuntimeException(
                'Неизвестный контроллер: ' . $class
            ),
        };
    };

    $router = new Router();

    require $rootPath . '/routes/web.php';

    $router->dispatch(
        $method,
        $_SERVER['REQUEST_URI'],
        $resolveController,
        $_POST
    );
} catch (Throwable $exception) {
    error_log((string) $exception);

    $isValidationError = $method === 'POST'
        && $exception instanceof InvalidArgumentException;

    http_response_code($isValidationError ? 422 : 500);

    if ($method === 'POST') {
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'message' => $isValidationError
                ? $exception->getMessage()
                : 'Не удалось обработать запрос',
        ], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/html; charset=utf-8');

        echo '<p>Не удалось обработать запрос.</p>';
    }
}