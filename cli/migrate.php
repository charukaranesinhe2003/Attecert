<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
load_env(base_path('.env'));

$host = env('DB_HOST', '127.0.0.1');
$port = (int) env('DB_PORT', '3306');
$name = (string) env('DB_DATABASE', 'attecert');
$user = (string) env('DB_USERNAME', 'root');
$password = (string) env('DB_PASSWORD', '');

$server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$server->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '', $name) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(80) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

foreach (glob(base_path('database/migrations/*.sql')) ?: [] as $file) {
    $version = basename($file);
    $exists = $pdo->prepare('SELECT version FROM schema_migrations WHERE version = ?');
    $exists->execute([$version]);
    if ($exists->fetchColumn()) {
        echo "Skipped {$version}\n";
        continue;
    }
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException('Cannot read ' . $file);
    }
    $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
    foreach ($parts as $statement) {
        $statement = trim($statement);
        if ($statement === '' || !str_contains(ltrim($statement), 'CREATE') && !str_contains(ltrim($statement), 'ALTER') && !str_contains(ltrim($statement), 'INSERT')) {
            if ($statement !== '' && preg_match('/^(CREATE|ALTER|INSERT|UPDATE|DELETE)\b/i', ltrim($statement))) {
                $pdo->exec($statement);
            }
            continue;
        }
        $pdo->exec($statement);
    }
    $insert = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
    $insert->execute([$version]);
    echo "Applied {$version}\n";
}
