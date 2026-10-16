<?php

declare(strict_types=1);

namespace App\Core;

final class Config
{
    private static array $items = [];

    public static function load(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = glob($directory . '/*.php') ?: [];
        if (empty($files) && is_readable($directory)) {
            $scanned = @scandir($directory) ?: [];
            foreach ($scanned as $item) {
                if (str_ends_with($item, '.php')) {
                    $files[] = $directory . '/' . $item;
                }
            }
        }

        foreach ($files as $file) {
            $key = basename($file, '.php');
            $value = require $file;
            if (is_array($value)) {
                self::$items[$key] = $value;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
