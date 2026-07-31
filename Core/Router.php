<?php
namespace App\Core;

/**
 * Router รองรับ GET/POST, พารามิเตอร์ {id} และ middleware
 */
final class Router
{
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $path, array $action, array $middleware = []): void
    {
        $this->add('GET', $path, $action, $middleware);
    }

    public function post(string $path, array $action, array $middleware = []): void
    {
        $this->add('POST', $path, $action, $middleware);
    }

    private function add(string $method, string $path, array $action, array $middleware): void
    {
        $path = '/' . trim($path, '/');
        $this->routes[$method][$path] = ['action' => $action, 'middleware' => $middleware];
    }

    public function dispatch(string $method, string $uri): void
    {
        foreach ($this->routes[$method] ?? [] as $route => $def) {
            $pattern = '#^' . preg_replace('#\{[a-zA-Z0-9_]+\}#', '([^/]+)', $route) . '$#';
            if (preg_match($pattern, $uri, $m)) {
                array_shift($m);

                foreach ($def['middleware'] as $mw) {
                    (new $mw())->handle();
                }

                [$class, $methodName] = $def['action'];
                (new $class())->{$methodName}(...$m);
                return;
            }
        }

        http_response_code(404);
        echo View::render('errors/404', ['title' => 'ไม่พบหน้า'], 'app');
    }
}
