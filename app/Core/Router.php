<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, callable> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET ' . $path] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST ' . $path] = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        $handler = $this->routes[$method . ' ' . $path] ?? null;
        if ($handler === null) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Página não encontrada'], Auth::check() ? 'layout' : 'guest');
            return;
        }
        $handler();
    }
}

