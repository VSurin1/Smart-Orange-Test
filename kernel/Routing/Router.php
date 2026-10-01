<?php

declare(strict_types=1);

namespace Kernel\Routing;

use Closure;

class Router
{
    private array $routes = [];

    public function get(string $path, array $action): void
    {
        $this->routes[$path]['GET'] = $action;
    }

    public function post(string $path, array $action): void
    {
        $this->routes[$path]['POST'] = $action;
    }

    public function dispatch(
        string $method,
        string $uri,
        Closure $resolveController,
        array $data = []
    ): void {
        // Убираем query string: /applications?page=2 → /applications.
        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || !isset($this->routes[$path])) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Страница не найдена';
            return;
        }

        if (!isset($this->routes[$path][$method])) {
            http_response_code(405);
            header('Allow: ' . implode(', ', array_keys($this->routes[$path])));
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Метод не поддерживается';
            return;
        }

        [$controllerClass, $action] = $this->routes[$path][$method];

        $controller = $resolveController($controllerClass);

        if ($method === 'POST') {
            $result = $controller->$action($data);

            header('Content-Type: application/json; charset=utf-8');

            $json = json_encode(
                $result,
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            http_response_code(201);
            echo $json;
        } else {
            header('Content-Type: text/html; charset=utf-8');

            $controller->$action();
        }
    }
}