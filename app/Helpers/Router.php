<?php

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, callable $handler): void
    {
        $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);
        $this->routes[$method][$path] = $handler;
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($path);

        if (isset($this->routes[$method][$path])) {
            call_user_func($this->routes[$method][$path]);
            return;
        }

        foreach ($this->routes[$method] ?? [] as $routePath => $handler) {
            $parameters = $this->match($routePath, $path);
            if ($parameters !== null) {
                call_user_func_array($handler, $parameters);
                return;
            }
        }

        if ($this->routeExistsForAnotherMethod($path)) {
            Response::error('Método HTTP não permitido para esta rota.', 405);
            return;
        }

        Response::error('Rota não encontrada.', 404);
    }

    private function normalizePath(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    private function routeExistsForAnotherMethod(string $path): bool
    {
        foreach ($this->routes as $routes) {
            foreach ($routes as $routePath => $_handler) {
                if ($routePath === $path || $this->match($routePath, $path) !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    private function match(string $routePath, string $requestPath): ?array
    {
        if (!str_contains($routePath, '{')) {
            return null;
        }

        $names = [];
        $quoted = preg_quote($routePath, '#');
        $pattern = preg_replace_callback(
            '/\\\{([A-Za-z_][A-Za-z0-9_]*)\\\}/',
            static function (array $matches) use (&$names): string {
                $names[] = $matches[1];
                return '([^/]+)';
            },
            $quoted
        );

        if (!preg_match('#^' . $pattern . '$#D', $requestPath, $matches)) {
            return null;
        }

        $parameters = [];
        foreach ($names as $index => $name) {
            $parameters[] = rawurldecode($matches[$index + 1]);
        }

        return $parameters;
    }
}
