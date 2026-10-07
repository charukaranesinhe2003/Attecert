<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function add(string $method, string $path, array $handler, array $middleware = []): void
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => '#^' . $pattern . '$#',
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request, Container $container): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }
            if (!preg_match($route['pattern'], $request->path(), $matches)) {
                continue;
            }
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            $destination = function (Request $request) use ($route, $params, $container): Response {
                [$class, $method] = $route['handler'];
                $controller = $container->get($class);
                $arguments = [];
                foreach ((new \ReflectionMethod($controller, $method))->getParameters() as $parameter) {
                    $type = $parameter->getType();
                    if ($type instanceof \ReflectionNamedType && $type->getName() === Request::class) {
                        $arguments[] = $request;
                        continue;
                    }
                    $name = $parameter->getName();
                    if (!array_key_exists($name, $params)) {
                        throw new \RuntimeException('Missing route parameter ' . $name);
                    }
                    $arguments[] = $params[$name];
                }
                $result = $controller->{$method}(...$arguments);
                if ($result instanceof Response) {
                    return $result;
                }

                return Response::html((string) $result);
            };
            $pipeline = array_reduce(
                array_reverse($route['middleware']),
                function (callable $next, string $middleware) use ($container): callable {
                    return function (Request $request) use ($middleware, $next, $container): Response {
                        $object = $this->makeMiddleware($middleware, $container);

                        return $object->handle($request, $next);
                    };
                },
                $destination
            );

            return $pipeline($request);
        }

        throw new HttpException(404, 'Page not found');
    }

    private function makeMiddleware(string $middleware, Container $container): object
    {
        if (str_starts_with($middleware, 'can:')) {
            $guard = $container->get(\App\Middleware\AuthorizeMiddleware::class);
            $guard->setPermission(substr($middleware, 4));

            return $guard;
        }

        return $container->get($middleware);
    }
}
