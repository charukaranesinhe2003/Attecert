<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/bootstrap/autoload.php';
$configuration = json_decode((string) fgets(STDIN), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^attecert_test_[a-f0-9]{16}$/D', (string) ($configuration['database']['database'] ?? ''))) {
    throw new RuntimeException('Only a disposable test database is allowed.');
}
(new ReflectionProperty(\App\Core\Config::class, 'items'))->setValue(null, $configuration);
$GLOBALS['app_container'] = new \App\Core\Container();
echo "READY\n"; flush();
if (trim((string) fgets(STDIN)) !== 'GO') { exit(1); }
$numbers = [];
for ($i = 0; $i < 25; $i++) { $numbers[] = app(\App\Services\NumberService::class)->nextTracking(); }
echo json_encode($numbers, JSON_THROW_ON_ERROR);
