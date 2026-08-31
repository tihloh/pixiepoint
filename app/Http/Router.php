<?php

declare(strict_types=1);

namespace PixiePoint\App\Http;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $this->routes[strtoupper($method)][$path] = $handler;
    }

    public function any(string $path, callable $handler): void
    {
        foreach (['GET','POST'] as $method) $this->add($method, $path, $handler);
    }

    public function dispatch(Request $request): mixed
    {
        $handler = $this->routes[$request->method][$request->path] ?? null;
        if (!$handler) return null;
        return $handler($request);
    }
}
