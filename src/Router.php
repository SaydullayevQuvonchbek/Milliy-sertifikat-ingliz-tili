<?php

declare(strict_types=1);

namespace App;

use App\Http\HttpError;
use App\Http\Request;

final class Router
{
    /** @var array<int, array{0:string,1:string,2:callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [strtoupper($method), $regex, $handler];
    }

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    public function dispatch(Request $request): mixed
    {
        $methodMatched = false;
        foreach ($this->routes as [$method, $regex, $handler]) {
            if (!preg_match($regex, $request->path, $m)) {
                continue;
            }
            $methodMatched = true;
            if ($method !== $request->method) {
                continue;
            }
            $request->params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            return $handler($request);
        }
        if ($methodMatched) {
            throw new HttpError(405, 'method_not_allowed', "Bu so'rov turi qo'llab-quvvatlanmaydi.");
        }
        throw new HttpError(404, 'not_found', 'Manzil topilmadi.');
    }
}
