<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $body,
        private array $files,
        private array $server,
        private array $cookies
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        $base = function_exists('web_base') ? web_base() : '';
        if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
            $path = substr($path, strlen($base)) ?: '/';
        } elseif (str_ends_with($base, '/public')) {
            $parentBase = substr($base, 0, -7);
            if ($parentBase !== '' && $parentBase !== '/' && ($path === $parentBase || str_starts_with($path, $parentBase . '/'))) {
                $path = substr($path, strlen($parentBase)) ?: '/';
            }
        }
        if (str_starts_with($path, '/public/')) {
            $path = substr($path, 7);
        } elseif ($path === '/public') {
            $path = '/';
        }
        if ($path === '' || !str_starts_with($path, '/')) {
            $path = '/' . ltrim($path, '/');
        }

        return new self($method, $path, $_GET, $_POST, $_FILES, $_SERVER, $_COOKIE);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->body;
    }

    public function only(array $keys): array
    {
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = $this->input($key);
        }

        return $data;
    }

    public function file(string $key): ?array
    {
        if (!isset($this->files[$key]) || ($this->files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $this->files[$key];
    }

    public function files(string $key): array
    {
        if (!isset($this->files[$key])) {
            return [];
        }
        $file = $this->files[$key];
        if (!is_array($file['name'])) {
            return ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$file];
        }
        $items = [];
        foreach ($file['name'] as $index => $name) {
            if (($file['error'][$index] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $items[] = [
                'name' => $name,
                'type' => $file['type'][$index] ?? '',
                'tmp_name' => $file['tmp_name'][$index] ?? '',
                'error' => $file['error'][$index] ?? UPLOAD_ERR_OK,
                'size' => $file['size'][$index] ?? 0,
            ];
        }

        return $items;
    }

    public function ip(): string
    {
        return substr((string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
    }

    public function userAgent(): string
    {
        return substr((string) ($this->server['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function wantsJson(): bool
    {
        $accept = (string) ($this->server['HTTP_ACCEPT'] ?? '');
        $xhr = (string) ($this->server['HTTP_X_REQUESTED_WITH'] ?? '');

        return str_contains($accept, 'application/json') || strcasecmp($xhr, 'XMLHttpRequest') === 0;
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        return (string) ($this->server[$key] ?? '');
    }

    public function isSecure(): bool
    {
        $https = (string) ($this->server['HTTPS'] ?? '');

        return $https !== '' && strcasecmp($https, 'off') !== 0;
    }
}
