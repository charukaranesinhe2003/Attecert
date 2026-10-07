<?php

declare(strict_types=1);

return [
    'name' => 'AtteCert',
    'env' => env('APP_ENV', 'production'),
    'debug' => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/'),
    'key' => (string) env('APP_KEY', ''),
    'timezone' => (string) env('APP_TIMEZONE', 'Asia/Colombo'),
    'locales' => ['en' => 'English', 'si' => 'සිංහල', 'ta' => 'தமிழ்'],
    'default_locale' => 'en',
    'upload_max_mb' => (int) env('UPLOAD_MAX_MB', '10'),
];
