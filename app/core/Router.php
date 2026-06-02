<?php

class Router
{
    private array $routes = [];

    public function get(string $path, $handler): self
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, $handler): self
    {
        return $this->add('POST', $path, $handler);
    }

    public function any(string $path, $handler): self
    {
        return $this->add('GET', $path, $handler)
            ->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, $handler): self
    {
        $this->routes[] = [
            'method'  => $method,
            'path'    => $this->normalize($path),
            'handler' => $handler,
        ];
        return $this;
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = $this->normalize(parse_url($uri, PHP_URL_PATH) ?: '/');
        $method = strtoupper($method);

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $params = $this->match($route['path'], $uri);
            if ($params === null) {
                continue;
            }
            $this->invoke($route['handler'], $params);
            return;
        }

        http_response_code(404);
        echo '404 — Trang không tồn tại';
    }

    private function match(string $pattern, string $uri): ?array
    {
        if ($pattern === $uri) {
            return [];
        }
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        if (!preg_match($regex, $uri, $m)) {
            return null;
        }
        $params = [];
        foreach ($m as $k => $v) {
            if (!is_int($k)) {
                $params[$k] = $v;
            }
        }
        return $params;
    }

    private function invoke($handler, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return;
        }
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $obj = new $class();
            call_user_func_array([$obj, $method], $params);
        }
    }
}
