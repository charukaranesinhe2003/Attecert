<?php

declare(strict_types=1);

use App\Core\Config;
use App\Services\SettingsService;
use App\Services\TranslationService;

function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return $value;
}

function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function base_path(string $path = ''): string
{
    $root = dirname(__DIR__, 2);

    return $path === '' ? $root : $root . '/' . ltrim($path, '/');
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function __(string $key, array $replace = []): string
{
    return app(\App\Services\TranslationService::class)->get($key, $replace);
}

function app(?string $id = null): mixed
{
    $container = $GLOBALS['app_container'] ?? null;
    if ($container === null) {
        throw new RuntimeException('Application container is not ready.');
    }
    if ($id === null) {
        return $container;
    }

    return $container->get($id);
}

function locale(): string
{
    $locale = (string) ($_SESSION['locale'] ?? config('app.default_locale', 'en'));
    $allowed = array_keys(config('app.locales', ['en' => 'English']));

    return in_array($locale, $allowed, true) ? $locale : 'en';
}

function url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
    }
    $locale = locale();
    if ($locale !== 'en' && !str_contains($path, 'lang=')) {
        $path .= (str_contains($path, '?') ? '&' : '?') . 'lang=' . rawurlencode($locale);
    }

    return $path;
}

function absolute_url(string $path = '/'): string
{
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }

    return rtrim((string) config('app.url'), '/') . url($path);
}

function asset(string $path): string
{
    $relative = '/assets/' . ltrim($path, '/');
    $full = base_path('public' . $relative);
    $version = is_file($full) ? (string) filemtime($full) : '1';

    return $relative . '?v=' . $version;
}

function redirect_to(string $path): \App\Core\Response
{
    return \App\Core\Response::redirect(url($path));
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function old(string $key, mixed $default = ''): mixed
{
    $old = $_SESSION['_view_old'] ?? [];

    return $old[$key] ?? $default;
}

function errors(): array
{
    return $_SESSION['_view_errors'] ?? [];
}

function error(string $key): string
{
    $errors = errors();
    $value = $errors[$key] ?? '';

    return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['_flash'][$key] = $message;

        return null;
    }
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);

    return $value;
}

function setting(string $key, mixed $default = null): mixed
{
    try {
        return app(SettingsService::class)->get($key, $default);
    } catch (Throwable) {
        return $default;
    }
}

function money(mixed $amount, ?string $currency = null): string
{
    $currency = $currency ?: (string) setting('currency', 'LKR');
    $formatted = number_format((float) $amount, 2);

    return $currency . ' ' . $formatted;
}

function format_date(?string $value, string $format = 'd M Y'): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $zone = new DateTimeZone((string) config('app.timezone', 'Asia/Colombo'));
    $date = new DateTime($value, $zone);

    return $date->format($format);
}

function format_datetime(?string $value): string
{
    return format_date($value, 'd M Y, H:i');
}

function icon(string $name, string $class = 'icon'): string
{
    return \App\Helpers\Icons::render($name, $class);
}

function content_block(string $key, string $default = ''): string
{
    try {
        return app(\App\Services\ContentService::class)->get($key, $default);
    } catch (Throwable) {
        return $default;
    }
}

function status_badge(string $label, string $tone = 'neutral'): string
{
    return '<span class="badge badge-' . e($tone) . '">' . e($label) . '</span>';
}

function paginator(array $page, string $path): string
{
    if (($page['pages'] ?? 1) <= 1) {
        return '';
    }
    $query = $_GET;
    unset($query['page']);
    $html = '<nav class="pagination" aria-label="Pagination">';
    for ($i = 1; $i <= $page['pages']; $i++) {
        $query['page'] = $i;
        $href = url($path . '?' . http_build_query($query));
        $current = $i === (int) $page['page'];
        $html .= '<a class="page-link' . ($current ? ' is-current' : '') . '" href="' . e($href) . '"'
            . ($current ? ' aria-current="page"' : '') . '>' . $i . '</a>';
    }
    $html .= '</nav>';

    return $html;
}

function selected($left, $right): string
{
    return (string) $left === (string) $right ? ' selected' : '';
}

function checked($value): string
{
    return $value ? ' checked' : '';
}

function account_customer(): ?array
{
    static $resolved = false;
    static $customer = null;
    if ($resolved) {
        return $customer;
    }
    $resolved = true;
    if (empty($_SESSION['user_id'])) {
        return $customer = null;
    }
    try {
        return $customer = app(\App\Services\AuthService::class)->customer();
    } catch (Throwable) {
        return $customer = null;
    }
}

function customer_code(array $customer): string
{
    return 'ATC-' . str_pad((string) $customer['id'], 6, '0', STR_PAD_LEFT);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }

    return $letters !== '' ? $letters : 'A';
}

function time_ago(?string $value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $diff = time() - strtotime($value);
    if ($diff < 60) {
        return __('time.just_now');
    }
    if ($diff < 3600) {
        return __('time.minutes', ['count' => (string) max(1, (int) floor($diff / 60))]);
    }
    if ($diff < 86400) {
        return __('time.hours', ['count' => (string) max(1, (int) floor($diff / 3600))]);
    }
    if ($diff < 86400 * 14) {
        return __('time.days', ['count' => (string) max(1, (int) floor($diff / 86400))]);
    }

    return format_date($value);
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'item';
}

function str_limit(string $value, int $limit = 140): string
{
    if (mb_strlen($value) <= $limit) {
        return $value;
    }

    return rtrim(mb_substr($value, 0, $limit - 1)) . '…';
}
