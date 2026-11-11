<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Maps "METHOD /path" to a handler. Paths may contain {placeholders},
 * e.g. /api/products/{slug}, whose values reach the handler via $request->params.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: callable(Request): Response}> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        // /api/products/{slug}  →  #^/api/products/(?P<slug>[^/]+)$#
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $path) . '$#';

        $this->routes[] = ['method' => $method, 'regex' => $regex, 'handler' => $handler];
    }

    public function dispatch(Request $request): Response
    {
        $pathExists = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $matches)) {
                continue;
            }
            $pathExists = true;
            if ($route['method'] !== $request->method) {
                continue;
            }

            // preg_match returns both numbered and named groups; keep only the named ones.
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            return ($route['handler'])($request->withParams($params));
        }

        // Right path but wrong method is a different mistake from a path that does not exist.
        throw $pathExists
            ? new HttpException(405, 'Method not allowed')
            : new HttpException(404, 'Not found');
    }
}
