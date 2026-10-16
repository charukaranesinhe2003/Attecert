<?php

declare(strict_types=1);

$database = (string) getenv('ATTECERT_PREVIEW_DATABASE');
if (PHP_SAPI !== 'cli-server' || !preg_match('/^attecert_test_[a-f0-9]{16}$/', $database)) {
    http_response_code(404);
    exit;
}
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$public = realpath(dirname(__DIR__) . '/public');
$file = realpath($public . $uri);
if ($uri !== '/' && $file !== false && str_starts_with($file, $public . DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}
require dirname(__DIR__) . '/vendor/autoload.php';
$kernel = require base_path('bootstrap/app.php');
$property = new ReflectionProperty(\App\Core\Config::class, 'items');
$items = $property->getValue();
if ($items['database']['database'] === $database) {
    http_response_code(500);
    exit('Preview database must be separate.');
}
$items['database']['database'] = $database;
$items['app']['storage_path'] = (string) getenv('ATTECERT_PREVIEW_STORAGE');
$items['app']['url'] = 'http://127.0.0.1:8091';
$items['mail']['host'] = '';
$property->setValue(null, $items);
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; script-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
$kernel->handle(\App\Core\Request::capture());
