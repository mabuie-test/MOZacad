<?php

declare(strict_types=1);

namespace App\Helpers;

final class Router
{
    private array $routes = [];
    private array $groupPrefixes = [''];
    private array $groupMiddleware = [[]];

    public function get(string $path, callable|array $handler, array $middleware = []): void { $this->add('GET', $path, $handler, $middleware); }
    public function post(string $path, callable|array $handler, array $middleware = []): void { $this->add('POST', $path, $handler, $middleware); }

    public function group(string $prefix, array $middleware, callable $callback): void
    {
        $currentPrefix = end($this->groupPrefixes) ?: '';
        $currentMiddleware = end($this->groupMiddleware) ?: [];

        $this->groupPrefixes[] = rtrim($currentPrefix . $prefix, '/');
        $this->groupMiddleware[] = array_merge($currentMiddleware, $middleware);

        $callback($this);

        array_pop($this->groupPrefixes);
        array_pop($this->groupMiddleware);
    }

    private function add(string $method, string $path, callable|array $handler, array $middleware = []): void
    {
        $prefix = end($this->groupPrefixes) ?: '';
        $scoped = ($prefix === '' ? '' : $prefix) . ($path === '/' ? '' : $path);
        $normalized = $scoped === '' ? '/' : $scoped;
        $normalized = $normalized !== '/' ? rtrim($normalized, '/') : '/';

        $inherited = end($this->groupMiddleware) ?: [];
        $this->routes[$method][] = ['path' => $normalized, 'handler' => $handler, 'middleware' => array_merge($inherited, $middleware)];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = $path !== '/' ? rtrim($path, '/') : '/';

        foreach ($this->routes[$method] ?? [] as $route) {
            $pattern = '#^' . preg_replace('#\{([^/]+)\}#', '(?P<$1>[^/]+)', $route['path']) . '$#';
            if (preg_match($pattern, $path, $matches) !== 1) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[] = ctype_digit($value) ? (int) $value : $value;
                }
            }

            $core = function () use ($route, $params): mixed {
                $handler = $route['handler'];
                if (is_array($handler)) {
                    [$class, $action] = $handler;
                    return (new $class())->{$action}(...$params);
                }

                return $handler(...$params);
            };

            $runner = array_reduce(
                array_reverse($route['middleware']),
                fn (callable $next, callable $middleware): callable => fn () => $middleware($next),
                $core,
            );

            $runner();
            return;
        }

        http_response_code(404);
        echo '404';
    }
}
