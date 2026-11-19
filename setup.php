<?php

declare(strict_types=1);

/**
 * AtteCert Browser-Based Pure-PHP Installer & Database Setup
 * Sets up tables and initial data without requiring SSH or Composer CLI.
 */

require_once __DIR__ . '/includes/init.php';

$message = null;
$error = null;

$envFile = __DIR__ . '/.env';
$defaultHost = env('DB_HOST', '127.0.0.1');
$defaultPort = env('DB_PORT', '3306');
$defaultDb   = env('DB_DATABASE', 'attecert');
$defaultUser = env('DB_USERNAME', 'root');
$defaultPass = env('DB_PASSWORD', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = trim((string) ($_POST['host'] ?? $defaultHost));
    $port = trim((string) ($_POST['port'] ?? $defaultPort));
    $dbName = trim((string) ($_POST['database'] ?? $defaultDb));
    $user = trim((string) ($_POST['username'] ?? $defaultUser));
    $pass = (string) ($_POST['password'] ?? $defaultPass);

    try {
        // 1. Connect to MySQL server
        $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        // 2. Create database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");

        // 3. Read SQL dump file
        $sqlFile = __DIR__ . '/database/attecert.sql';
        if (!is_file($sqlFile)) {
            throw new RuntimeException("SQL file not found at {$sqlFile}.");
        }

        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new RuntimeException("Cannot read {$sqlFile}.");
        }

        // 4. Execute base dump
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
        $pdo->exec($sql);

        // 4b. Execute all pending migrations
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(80) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach (glob(__DIR__ . '/database/migrations/*.sql') ?: [] as $file) {
            $version = basename($file);
            if (in_array($version, $applied, true)) {
                continue;
            }
            $migSql = (string) file_get_contents($file);
            $parts = preg_split('/;\s*(?:\r?\n|$)/', $migSql) ?: [];
            foreach ($parts as $statement) {
                $statement = trim($statement);
                if ($statement !== '' && preg_match('/^(CREATE|ALTER|INSERT|UPDATE|DELETE)\b/i', ltrim($statement))) {
                    try {
                        $pdo->exec($statement);
                    } catch (Throwable) {}
                }
            }
            $ins = $pdo->prepare('INSERT IGNORE INTO schema_migrations (version) VALUES (?)');
            $ins->execute([$version]);
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

        // 5. Update .env if writable
        $envContent = <<<ENV
APP_NAME=AtteCert
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_HOST={$host}
DB_PORT={$port}
DB_DATABASE={$dbName}
DB_USERNAME={$user}
DB_PASSWORD={$pass}

MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=hello@attecert.lk
MAIL_FROM_NAME=AtteCert
ENV;
        @file_put_contents($envFile, $envContent);

        $tablesCount = count($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN));
        $message = "Database `{$dbName}` successfully installed with {$tablesCount} tables and seed data!";
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AtteCert 1-Click Database Setup</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; padding: 40px 20px; line-height: 1.6; }
        .box { max-width: 650px; margin: 0 auto; background: #1e293b; border-radius: 12px; padding: 32px; box-shadow: 0 10px 25px rgba(0,0,0,0.4); border: 1px solid #334155; }
        h1 { margin-top: 0; color: #38bdf8; font-size: 1.75rem; }
        .alert-ok { background: #065f46; color: #a7f3d0; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #059669; }
        .alert-err { background: #881337; color: #fecdd3; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #be123c; }
        label { display: block; font-size: 0.9rem; font-weight: 600; margin-bottom: 6px; color: #cbd5e1; }
        input { width: 100%; box-sizing: border-box; padding: 10px 14px; border-radius: 6px; border: 1px solid #475569; background: #0f172a; color: white; margin-bottom: 16px; font-size: 0.95rem; }
        .btn { display: inline-block; background: #0284c7; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1rem; width: 100%; text-align: center; text-decoration: none; }
        .btn:hover { background: #0369a1; }
        .links { margin-top: 24px; border-top: 1px solid #334155; padding-top: 20px; display: flex; flex-direction: column; gap: 10px; }
        .links a { color: #38bdf8; text-decoration: none; font-weight: 500; }
        .links a:hover { text-decoration: underline; }
        .note { font-size: 0.85rem; color: #94a3b8; margin-top: 8px; }
        .creds { background: #0f172a; padding: 12px; border-radius: 6px; margin: 12px 0; font-family: monospace; font-size: 0.85rem; }
    </style>
</head>
<body>
<div class="box">
    <h1>AtteCert 1-Click Pure-PHP Setup</h1>
    <p>Setup database tables and sample records without needing Composer or SSH commands.</p>

    <?php if ($message): ?>
        <div class="alert-ok">
            <strong>&check; Success!</strong> <?= htmlspecialchars($message) ?>
        </div>
        <div class="creds">
            <strong>Default Accounts:</strong><br>
            Super Admin: <code>admin@attecert.local</code> | Password: <code>Admin@12345</code><br>
            Customer: <code>customer@attecert.local</code> | Password: <code>Customer@12345</code><br>
            Sample Tracking Number: <code>ATT-2026-000001</code>
        </div>
        <div class="links">
            <a href="index.php" class="btn">&rarr; Open Website Homepage</a>
            <a href="login.php">&rarr; Open Login Page</a>
            <a href="admin/index.php">&rarr; Open Admin Dashboard</a>
            <a href="customer/index.php">&rarr; Open Customer Portal</a>
        </div>
    <?php else: ?>
        <?php if ($error): ?>
            <div class="alert-err">
                <strong>&cross; Setup Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="setup.php">
            <label for="host">MySQL Host</label>
            <input type="text" id="host" name="host" value="<?= htmlspecialchars($defaultHost) ?>" required>

            <label for="port">MySQL Port</label>
            <input type="text" id="port" name="port" value="<?= htmlspecialchars((string) $defaultPort) ?>" required>

            <label for="database">Database Name</label>
            <input type="text" id="database" name="database" value="<?= htmlspecialchars($defaultDb) ?>" required>

            <label for="username">Database Username</label>
            <input type="text" id="username" name="username" value="<?= htmlspecialchars($defaultUser) ?>" required>

            <label for="password">Database Password</label>
            <input type="password" id="password" name="password" value="<?= htmlspecialchars($defaultPass) ?>" placeholder="(leave blank if none)">

            <button type="submit" class="btn">Install Database & Seed Data</button>
            <p class="note">This will import <code>database/attecert.sql</code> into the specified database.</p>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
