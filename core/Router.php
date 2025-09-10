<?php

declare(strict_types=1);


namespace Core;


final class Router
{
    /** @var array<string, callable> */
    private array $routes = [];


    public function get(string $path, callable $action): void
    {
        $this->map('GET', $path, $action);
    }
    public function post(string $path, callable $action): void
    {
        $this->map('POST', $path, $action);
    }


    private function map(string $method, string $path, callable $action): void
    {
        $key = $method . ' ' . $path;
        $this->routes[$key] = $action;
    }


    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';


        $action = $this->routes[$method . ' ' . $uri] ?? null;
        if ($action) {
            $action();
            return;
        }


        // Match dinâmico /operations/{id}
        foreach ($this->routes as $key => $cb) {
            [$m, $p] = explode(' ', $key, 2);
            if ($m !== $method) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{[^/]+\}#', '([^/]+)', $p) . '$#';
            if (preg_match($regex, (string)$uri, $mats)) {
                array_shift($mats);
                $cb(...$mats);
                return;
            }
        }


        http_response_code(404);
        echo 'Not Found';
    }
}
