<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int,array{method:string,pattern:string,regex:string,handler:mixed,middleware:array}> */
    private array $routes = [];

    private array $groupMiddleware = [];

    private string $groupPrefix = '';

    public function group(array $options, callable $callback): void
    {
        $previousMiddleware = $this->groupMiddleware;
        $previousPrefix = $this->groupPrefix;

        $this->groupMiddleware = array_merge($this->groupMiddleware, $options['middleware'] ?? []);
        $this->groupPrefix .= $options['prefix'] ?? '';

        $callback($this);

        $this->groupMiddleware = $previousMiddleware;
        $this->groupPrefix = $previousPrefix;
    }

    public function get(string $pattern, mixed $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, mixed $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, mixed $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, mixed $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    private function add(string $method, string $pattern, mixed $handler): void
    {
        $fullPattern = rtrim($this->groupPrefix . $pattern, '/');
        $fullPattern = $fullPattern === '' ? '/' : $fullPattern;

        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $fullPattern);

        $this->routes[] = [
            'method' => $method,
            'pattern' => $fullPattern,
            'regex' => '#^' . $regex . '$#u',
            'handler' => $handler,
            'middleware' => $this->groupMiddleware,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method === 'HEAD' ? 'GET' : $request->method;

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }

            $params = array_filter($matches, static fn ($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
            $request->routeParams = $params;

            $pipeline = array_reverse($route['middleware']);
            $core = function (Request $req) use ($route): Response {
                return $this->invoke($route['handler'], $req);
            };

            $next = $core;
            foreach ($pipeline as $middlewareClass) {
                $middleware = new $middlewareClass();
                $next = function (Request $req) use ($middleware, $next): Response {
                    return $middleware->handle($req, $next);
                };
            }

            return $next($request);
        }

        return Response::html($this->notFoundBody(), 404);
    }

    private function invoke(mixed $handler, Request $request): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class();

            return $controller->$method($request);
        }

        return $handler($request);
    }

    private function notFoundBody(): string
    {
        return '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8">'
            . '<body style="font-family:sans-serif;text-align:center;padding:4rem">'
            . '<h1>۴۰۴</h1><p>این صفحه پیدا نشد.</p></body></html>';
    }
}
