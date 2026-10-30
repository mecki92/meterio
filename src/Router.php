<?php
declare(strict_types=1);

final class Router
{
    private array $routes = [];

    public function get(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, array $handler): void
    {
        $regex = preg_replace('#\{id\}#', '(\d+)', $pattern);
        $regex = preg_replace('#\{token\}#', '([A-Za-z0-9]{16,128})', $regex);
        $this->routes[] = [$method, '#^' . $regex . '$#', $handler];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path   = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
        $base   = base_path();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim($path, '/');

        foreach ($this->routes as [$m, $regex, $handler]) {
            if ($m === $method && preg_match($regex, $path, $mm)) {
                if ($method === 'POST') {
                    csrf_verify();
                }
                array_shift($mm);
                $args = array_map(static fn($v) => ctype_digit($v) ? (int) $v : $v, $mm);
                [$class, $fn] = $handler;
                (new $class())->$fn(...$args);
                return;
            }
        }
        abort(404, 'Die angeforderte Seite existiert nicht.');
    }
}
