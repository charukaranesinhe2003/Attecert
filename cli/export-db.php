<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/bootstrap/app.php';

$pdo = app(App\Core\Database::class)->pdo();
$database = (string) config('database.database');
$target = $argv[1] ?? base_path('database/attecert.sql');

$out = fopen($target, 'wb');
if ($out === false) {
    fwrite(STDERR, "Cannot write {$target}\n");
    exit(1);
}

$write = static function (string $sql) use ($out): void {
    fwrite($out, $sql);
};

$write("-- AtteCert export of `{$database}`\n");
$write('-- Generated ' . date('Y-m-d H:i:s') . "\n\n");
$write("SET NAMES utf8mb4;\n");
$write("SET FOREIGN_KEY_CHECKS = 0;\n");
$write("SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");
$write("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n");
$write("USE `{$database}`;\n\n");

$tables = $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_NUM);
foreach ($tables as [$table]) {
    $safe = str_replace('`', '``', $table);
    $create = $pdo->query('SHOW CREATE TABLE `' . $safe . '`')->fetch(PDO::FETCH_ASSOC);
    $generated = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND EXTRA LIKE \'%GENERATED%\''
    );
    $generated->execute([$database, $table]);
    $skip = array_flip($generated->fetchAll(PDO::FETCH_COLUMN));

    $write("DROP TABLE IF EXISTS `{$safe}`;\n");
    $write($create['Create Table'] . ";\n\n");

    $rows = $pdo->query('SELECT * FROM `' . $safe . '`');
    $batch = [];
    $columns = null;
    $flush = static function () use (&$batch, &$columns, $write, $safe): void {
        if ($batch === [] || $columns === null) {
            return;
        }
        $write('INSERT INTO `' . $safe . '` (`' . implode('`, `', $columns) . "`) VALUES\n" . implode(",\n", $batch) . ";\n");
        $batch = [];
    };

    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
        if ($columns === null) {
            $columns = array_values(array_filter(array_keys($row), static fn (string $column): bool => !isset($skip[$column])));
        }
        $values = [];
        foreach ($columns as $column) {
            $value = $row[$column];
            $values[] = $value === null ? 'NULL' : $pdo->quote((string) $value);
        }
        $batch[] = '(' . implode(', ', $values) . ')';
        if (count($batch) === 100) {
            $flush();
        }
    }
    $flush();
    $write("\n");
}

$write("SET FOREIGN_KEY_CHECKS = 1;\n");
fclose($out);

echo "Exported {$database} to {$target}\n";
