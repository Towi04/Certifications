<?php

declare(strict_types=1);

namespace App\Http;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    /** @var array<string, string> */
    private static array $routeParams = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$this->normalize($path)] = $handler;
    }

    public static function param(string $name, string $default = ''): string
    {
        return self::$routeParams[$name] ?? $default;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = $this->normalize($path);
        self::$routeParams = [];
        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            foreach ($this->routes[$method] ?? [] as $pattern => $routeHandler) {
                $params = $this->matchRoute($pattern, $path);
                if ($params !== null) {
                    self::$routeParams = $params;
                    $handler = $routeHandler;
                    break;
                }
            }
        }

        if ($handler === null) {
            http_response_code(404);
            echo 'Página no encontrada.';
            return;
        }

        $handler();
    }

  /**
   * @return array<string, string>|null
   */
    private function matchRoute(string $pattern, string $path): ?array
    {
        $pattern = $this->normalize($pattern);
        $path = $this->normalize($path);
        if ($pattern === $path) {
            return [];
        }

        $patternParts = explode('/', trim($pattern, '/'));
        $pathParts = explode('/', trim($path, '/'));
        if (count($patternParts) !== count($pathParts)) {
            return null;
        }

        $params = [];
        foreach ($patternParts as $i => $segment) {
            if (preg_match('/^\{(.+)\}$/', $segment, $matches)) {
                $params[$matches[1]] = $pathParts[$i];
                continue;
            }
            if ($segment !== $pathParts[$i]) {
                return null;
            }
        }

        return $params;
    }

    private function normalize(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }
}
