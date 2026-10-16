<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>AtteCert cPanel Diagnostics & Repair</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; line-height: 1.5; padding: 24px; max-width: 900px; margin: 0 auto; background: #f8fafc; color: #1e293b; }
        h1 { color: #0f172a; margin-bottom: 8px; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .card h2 { margin-top: 0; font-size: 1.25rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px; }
        .ok { color: #16a34a; font-weight: bold; }
        .warn { color: #d97706; font-weight: bold; }
        .error { color: #dc2626; font-weight: bold; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }
        pre { background: #0f172a; color: #f8fafc; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 0.85em; }
        .btn { display: inline-block; background: #2563eb; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; }
    </style>
</head>
<body>
<h1>AtteCert Deployment Diagnostics & Self-Repair</h1>
<p>This script checks your cPanel file structure, permissions, configuration, and database connection.</p>

<div class="card">
    <h2>1. Root Directory & Core Folders</h2>
    <?php
    $possibleRoots = [
        dirname(__DIR__),
        __DIR__,
        '/home/azharbio/attecert.com'
    ];
    $basePath = null;
    foreach ($possibleRoots as $cand) {
        if (is_file($cand . '/public/index.php') || is_dir($cand . '/app')) {
            $basePath = realpath($cand) ?: $cand;
            break;
        }
    }
    echo "<p>Detected Base Path: <code>" . htmlspecialchars((string) $basePath) . "</code></p>";

    $requiredFolders = ['app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'storage', 'vendor'];
    echo "<ul>";
    foreach ($requiredFolders as $folder) {
        $full = $basePath . '/' . $folder;
        if (is_dir($full)) {
            $perms = substr(sprintf('%o', fileperms($full)), -4);
            $readable = is_readable($full) ? 'Readable' : '<span class="error">Not readable</span>';
            echo "<li><code>/{$folder}</code>: <span class=\"ok\">EXISTS</span> (Permissions: {$perms}, {$readable})</li>";
        } else {
            echo "<li><code>/{$folder}</code>: <span class=\"error\">MISSING!</span> &larr; <em>Please upload this folder to {$basePath}/{$folder}</em></li>";
        }
    }
    echo "</ul>";
    ?>
</div>

<div class="card">
    <h2>2. Config Files Check</h2>
    <?php
    $configDir = $basePath . '/config';
    if (!is_dir($configDir)) {
        echo "<p class=\"error\">CRITICAL: The <code>config/</code> directory does not exist on your server! Please upload the <code>config/</code> folder from your computer to <code>{$basePath}/config/</code>.</p>";
    } else {
        $configPerms = substr(sprintf('%o', fileperms($configDir)), -4);
        echo "<p><code>config/</code> folder permissions: <strong>{$configPerms}</strong></p>";

        $globFiles = glob($configDir . '/*.php') ?: [];
        echo "<p>Files found via <code>glob()</code>: " . count($globFiles) . "</p>";

        $scandirFiles = @scandir($configDir) ?: [];
        $phpFiles = array_filter($scandirFiles, fn($f) => str_ends_with($f, '.php'));
        echo "<p>Files found via <code>scandir()</code>: " . count($phpFiles) . " (" . htmlspecialchars(implode(', ', $phpFiles)) . ")</p>";

        foreach (['app.php', 'database.php', 'mail.php'] as $cfg) {
            $cfgFile = $configDir . '/' . $cfg;
            if (is_file($cfgFile)) {
                $content = @include $cfgFile;
                if (is_array($content)) {
                    echo "<p class=\"ok\">&check; <code>{$cfg}</code> loaded successfully as array.</p>";
                } else {
                    echo "<p class=\"error\">&cross; <code>{$cfg}</code> exists but did not return array.</p>";
                }
            } else {
                echo "<p class=\"error\">&cross; <code>{$cfg}</code> is MISSING from <code>config/</code> directory!</p>";
            }
        }
    }
    ?>
</div>

<div class="card">
    <h2>3. Environment (.env) & Database Test</h2>
    <?php
    $envPath = $basePath . '/.env';
    if (!is_file($envPath)) {
        echo "<p class=\"warn\">Warning: <code>.env</code> file not found at <code>{$envPath}</code>.<br>The app will attempt to use default settings.</p>";
    } else {
        echo "<p class=\"ok\">&check; <code>.env</code> file found at <code>{$envPath}</code>.</p>";
        // Simple parse
        $envData = [];
        foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
            [$k, $v] = explode('=', $line, 2);
            $envData[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        }

        $host = $envData['DB_HOST'] ?? '127.0.0.1';
        $port = $envData['DB_PORT'] ?? '3306';
        $db   = $envData['DB_DATABASE'] ?? 'attecert';
        $user = $envData['DB_USERNAME'] ?? 'root';
        $pass = $envData['DB_PASSWORD'] ?? '';

        echo "<p>Parsed DB config: Host=<code>{$host}:{$port}</code>, Database=<code>{$db}</code>, User=<code>{$user}</code></p>";

        echo "<p>Testing MySQL PDO connection...</p>";
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ]);
            echo "<p class=\"ok\">&check; <strong>DATABASE CONNECTION SUCCESSFUL!</strong></p>";

            // Check if tables exist
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            echo "<p>Tables found in database: <strong>" . count($tables) . "</strong> (" . htmlspecialchars(implode(', ', array_slice($tables, 0, 8))) . (count($tables) > 8 ? '...' : '') . ")</p>";
            if (empty($tables)) {
                echo "<p class=\"warn\">Warning: Database is connected but has no tables. Remember to import <code>database/schema.sql</code>.</p>";
            }
        } catch (Throwable $e) {
            echo "<p class=\"error\">&cross; <strong>Database Connection Failed:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    ?>
</div>

<div class="card">
    <h2>4. Automatic Full Permission Fix</h2>
    <?php
    $action = $_GET['fix_perms'] ?? '';
    if ($action === '1') {
        echo "<p>Recursively fixing permissions across <code>{$basePath}</code>...</p>";
        $fixedDirs = 0;
        $fixedFiles = 0;

        @chmod($basePath, 0755);
        $fixedDirs++;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $p = $item->getPathname();
            // Don't touch git
            if (str_contains($p, '/.git') || str_contains($p, '\\.git')) continue;

            if ($item->isDir()) {
                if (@chmod($p, 0755)) $fixedDirs++;
            } elseif ($item->isFile()) {
                if (@chmod($p, 0644)) $fixedFiles++;
            }
        }

        echo "<p class=\"ok\">&check; Successfully updated <strong>{$fixedDirs}</strong> folders to <code>0755</code> and <strong>{$fixedFiles}</strong> files to <code>0644</code>.</p>";
    } else {
        echo "<p>If you suspect file permission issues on any folder, click the button below to reset all folders to <code>755</code> and files to <code>644</code>:</p>";
        echo "<a class=\"btn\" href=\"?fix_perms=1\">Click Here to Fix All File/Folder Permissions</a>";
    }
    ?>
</div>

<div class="card">
    <p class="error"><strong>SECURITY REMINDER:</strong> Delete this <code>diagnose.php</code> file from cPanel File Manager after you are finished troubleshooting!</p>
</div>
</body>
</html>
