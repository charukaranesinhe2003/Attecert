<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = []): string
    {
        $path = dirname(__DIR__, 2) . '/resources/views/' . $view . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException('View not found: ' . $view);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $path;

            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }

    public static function page(string $view, array $data = [], string $layout = 'layouts/public'): string
    {
        $content = self::render($view, $data);

        return self::render($layout, array_merge($data, ['content' => $content]));
    }
}
