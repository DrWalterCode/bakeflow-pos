<?php
declare(strict_types=1);

namespace App\Core;

class Router
{
    private static array $routes = [];

    public static function get(string $path, callable|array $handler): void
    {
        self::$routes['GET'][$path] = $handler;
    }

    public static function post(string $path, callable|array $handler): void
    {
        self::$routes['POST'][$path] = $handler;
    }

    public static function dispatch(): void
    {
        $requestMethod = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $method = $requestMethod === 'HEAD' ? 'GET' : $requestMethod;
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri    = rtrim($uri, '/') ?: '/';

        // Exact match first
        if (isset(self::$routes[$method][$uri])) {
            self::invoke(self::$routes[$method][$uri], [], $requestMethod);
            return;
        }

        // Parameterised routes
        foreach (self::$routes[$method] ?? [] as $route => $handler) {
            $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $route);
            $pattern = '#^' . $pattern . '$#';
            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                self::invoke($handler, $params, $requestMethod);
                return;
            }
        }

        http_response_code(404);
        echo '404 Not Found';
    }

    private static function call(callable|array $handler, array $params = []): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $instance = new $class();
            $instance->$method(...array_values($params));
        } else {
            $handler(...array_values($params));
        }
    }

    private static function invoke(callable|array $handler, array $params, string $requestMethod): void
    {
        if ($requestMethod === 'HEAD') {
            ob_start();
            self::call($handler, $params);
            ob_end_clean();
            return;
        }

        self::call($handler, $params);
    }
}
