<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Container;
use App\Core\Database;
use App\Services\MailService;

// No credentials in command arguments or files, and no route to a live recipient/provider.
$configuration = json_decode((string) fgets(STDIN), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^attecert_test_[a-f0-9]{16}$/', (string) ($configuration['database']['database'] ?? ''))
    || ($configuration['mail']['host'] ?? '') !== '127.0.0.1') {
    throw new RuntimeException('Mail test worker requires a disposable database and loopback SMTP.');
}
(new ReflectionProperty(Config::class, 'items'))->setValue(null, $configuration);
date_default_timezone_set((string) config('app.timezone', 'Asia/Colombo'));
$GLOBALS['app_container'] = new Container();
$_SESSION = ['locale' => 'en'];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
if (!preg_match('/^attecert_test_[a-f0-9]{16}$/', (string) app(Database::class)->fetchColumn('SELECT DATABASE()'))) {
    throw new RuntimeException('Connected database is not disposable.');
}
echo "READY\n";
flush();
if (trim((string) fgets(STDIN)) !== 'GO') exit(1);
try {
    echo json_encode(['ok' => true, 'results' => app(MailService::class)->processQueue(1)], JSON_THROW_ON_ERROR);
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'error' => get_class($exception)], JSON_THROW_ON_ERROR);
}
