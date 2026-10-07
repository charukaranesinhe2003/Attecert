<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        private string $body = '',
        private int $status = 200,
        private array $headers = []
    ) {
    }

    public static function html(string $html, int $status = 200, array $headers = []): self
    {
        return new self($html, $status, array_merge(['Content-Type' => 'text/html; charset=UTF-8'], $headers));
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public static function download(string $absolutePath, string $downloadName, string $mime): self
    {
        return new self($absolutePath, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . self::safeName($downloadName) . '"',
            'Content-Length' => (string) filesize($absolutePath),
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            '__file' => '1',
        ]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        $file = isset($this->headers['__file']);
        foreach ($this->headers as $name => $value) {
            if ($name === '__file') {
                continue;
            }
            header($name . ': ' . $value);
        }
        if ($file) {
            readfile($this->body);

            return;
        }
        echo $this->body;
    }

    private static function safeName(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'download';

        return str_replace(['"', "\r", "\n"], '', $clean);
    }
}
