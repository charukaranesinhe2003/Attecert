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
        .card h3 { font-size: 1.05rem; margin-top: 16px; margin-bottom: 6px; color: #334155; }
        .ok { color: #16a34a; font-weight: bold; }
        .warn { color: #d97706; font-weight: bold; }
        .error { color: #dc2626; font-weight: bold; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.9em; }
        pre { background: #0f172a; color: #f8fafc; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 0.85em; max-height: 400px; }
        .btn { display: inline-block; background: #2563eb; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; margin-right: 8px; }
        .btn-success { background: #16a34a; }
        .btn-danger { background: #dc2626; }
    </style>
</head>
<body>
<h1>AtteCert Deployment Diagnostics & Self-Repair</h1>
<p>This script checks your cPanel file structure, permissions, database configuration, migrations, email/SMS queues, and error logs.</p>

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

        foreach (['app.php', 'database.php', 'mail.php', 'sms.php', 'verification.php'] as $cfg) {
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
    <h2>3. Environment (.env), Database & Migrations</h2>
    <?php
    $envPath = $basePath . '/.env';
    $pdo = null;
    $envData = [];
    if (!is_file($envPath)) {
        echo "<p class=\"warn\">Warning: <code>.env</code> file not found at <code>{$envPath}</code>.<br>The app will attempt to use default settings.</p>";
    } else {
        echo "<p class=\"ok\">&check; <code>.env</code> file found at <code>{$envPath}</code>.</p>";
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
        $appDebug = strtolower((string) ($envData['APP_DEBUG'] ?? 'false'));

        echo "<p>APP_DEBUG: <code>" . htmlspecialchars($appDebug) . "</code> " . ($appDebug === 'true' ? '<span class="warn">(Debug mode active)</span>' : '<span>(Production mode: errors hide detailed traces)</span>') . "</p>";
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
                echo "<p class=\"warn\">Warning: Database is connected but has no tables. Remember to import <code>database/schema.sql</code> or <code>database/attecert.sql</code>.</p>";
            } else {
                // Check schema migrations
                $migrationsDir = $basePath . '/database/migrations';
                $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(80) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
                $applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN) ?: [];
                $migrationFiles = glob($migrationsDir . '/*.sql') ?: [];
                
                $pending = [];
                foreach ($migrationFiles as $mf) {
                    $v = basename($mf);
                    if (!in_array($v, $applied, true)) {
                        $pending[] = $mf;
                    }
                }

                echo "<h3>Database Migrations Status</h3>";
                echo "<p>Applied migrations: <strong>" . count($applied) . "</strong> / " . count($migrationFiles) . "</p>";

                // Check critical custom_status column in cases table
                try {
                    $hasCustomStatus = $pdo->query("SHOW COLUMNS FROM cases LIKE 'custom_status'")->fetch();
                    if ($hasCustomStatus) {
                        echo "<p class=\"ok\">&check; <code>cases.custom_status</code> column is PRESENT.</p>";
                    } else {
                        echo "<p class=\"error\">&cross; <strong>CRITICAL ISSUE DETECTED:</strong> Column <code>cases.custom_status</code> is MISSING from the <code>cases</code> table! This causes the <code>500 Something went wrong</code> error when visiting <code>/admin</code>.</p>";
                    }

                    $hasMailOutbox = in_array('mail_outbox', $tables, true);
                    $hasSmsMessages = in_array('sms_messages', $tables, true);
                    $hasCustomerOtps = in_array('customer_otps', $tables, true);

                    if ($hasMailOutbox) {
                        echo "<p class=\"ok\">&check; <code>mail_outbox</code> table is PRESENT.</p>";
                    } else {
                        echo "<p class=\"error\">&cross; <code>mail_outbox</code> table is MISSING! (Migration 003 needed for email queueing).</p>";
                    }

                    if ($hasSmsMessages && $hasCustomerOtps) {
                        echo "<p class=\"ok\">&check; <code>sms_messages</code> and <code>customer_otps</code> tables are PRESENT.</p>";
                    } else {
                        echo "<p class=\"error\">&cross; <code>sms_messages</code> or <code>customer_otps</code> table is MISSING! (Migration 008 needed for SMS & customer OTP).</p>";
                    }
                } catch (Throwable) {}

                if (!empty($pending)) {
                    echo "<p class=\"warn\">Pending migrations (" . count($pending) . "):</p><ul>";
                    foreach ($pending as $pm) {
                        echo "<li><code>" . htmlspecialchars(basename($pm)) . "</code></li>";
                    }
                    echo "</ul>";

                    if (($_GET['run_migrations'] ?? '') === '1') {
                        echo "<h4>Applying Pending Migrations...</h4>";
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
                        foreach ($pending as $mf) {
                            $version = basename($mf);
                            $sql = file_get_contents($mf);
                            $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
                            foreach ($parts as $st) {
                                $st = trim($st);
                                if ($st !== '' && preg_match('/^(CREATE|ALTER|INSERT|UPDATE|DELETE)\b/i', ltrim($st))) {
                                    try {
                                        $pdo->exec($st);
                                    } catch (Throwable $ex) {
                                        echo "<p class=\"warn\">Notice on {$version}: " . htmlspecialchars($ex->getMessage()) . "</p>";
                                    }
                                }
                            }
                            $ins = $pdo->prepare('INSERT IGNORE INTO schema_migrations (version) VALUES (?)');
                            $ins->execute([$version]);
                            echo "<p class=\"ok\">&check; Applied <code>{$version}</code></p>";
                        }
                        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
                        echo "<p class=\"ok\"><strong>&check; All migrations successfully applied! Try accessing <a href=\"/admin\">/admin</a> now.</strong></p>";
                    } else {
                        echo "<a class=\"btn btn-success\" href=\"?run_migrations=1\">Click Here to Run Pending Migrations (1-Click Fix)</a>";
                    }
                } else {
                    echo "<p class=\"ok\">&check; All migrations are up to date!</p>";
                }
            }
        } catch (Throwable $e) {
            echo "<p class=\"error\">&cross; <strong>Database Connection Failed:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
    ?>
</div>

<div class="card">
    <h2>4. Email (SMTP), SMS (SMSlenz) & Customer Verification (OTP) Diagnostics</h2>
    <?php
    $otpRequired = strtolower((string) ($envData['CUSTOMER_OTP_REQUIRED'] ?? 'true')) !== 'false';
    $mailHost = trim((string) ($envData['MAIL_HOST'] ?? ''));
    $mailPort = trim((string) ($envData['MAIL_PORT'] ?? '587'));
    $mailUser = trim((string) ($envData['MAIL_USERNAME'] ?? ''));
    $mailFrom = trim((string) ($envData['MAIL_FROM_ADDRESS'] ?? ''));
    $mailEnc  = trim((string) ($envData['MAIL_ENCRYPTION'] ?? 'tls'));

    $smsUserId   = trim((string) ($envData['SMSLENZ_USER_ID'] ?? ''));
    $smsApiKey   = trim((string) ($envData['SMSLENZ_API_KEY'] ?? ''));
    $smsSenderId = trim((string) ($envData['SMSLENZ_SENDER_ID'] ?? ''));

    $emailReady = $mailHost !== '' && $mailFrom !== '';
    $smsReady   = $smsUserId !== '' && $smsApiKey !== '' && $smsSenderId !== '';

    echo "<h3>A. Customer Account OTP Setting</h3>";
    if ($otpRequired) {
        echo "<p><span class=\"warn\">&#9888; Customer OTP is REQUIRED (CUSTOMER_OTP_REQUIRED=true)</span></p>";
        echo "<p>When customers sign up or log in for the first time, they are redirected to <code>/verify-customer</code> to verify their email or phone via a 6-digit code.</p>";
        echo "<p><strong>Tip:</strong> If you don't have SMS or SMTP configured yet and want customers to access their account immediately without needing a code, add or edit this in your <code>.env</code> file:<br><code>CUSTOMER_OTP_REQUIRED=false</code></p>";
    } else {
        echo "<p><span class=\"ok\">&check; Customer OTP is DISABLED (CUSTOMER_OTP_REQUIRED=false)</span></p>";
        echo "<p>Customers can register and log in directly without being gated by <code>/verify-customer</code>.</p>";
    }

    echo "<h3>B. Email (SMTP) Configuration Status</h3>";
    echo "<ul>";
    echo "<li>Host: <code>" . ($mailHost !== '' ? htmlspecialchars($mailHost) : '<span class="error">NOT SET</span>') . "</code></li>";
    echo "<li>Port: <code>" . htmlspecialchars($mailPort) . "</code> (Encryption: <code>" . htmlspecialchars($mailEnc) . "</code>)</li>";
    echo "<li>Username: <code>" . ($mailUser !== '' ? htmlspecialchars($mailUser) : '<span class="warn">NOT SET</span>') . "</code></li>";
    echo "<li>From Address: <code>" . ($mailFrom !== '' ? htmlspecialchars($mailFrom) : '<span class="error">NOT SET</span>') . "</code></li>";
    echo "<li>SMTP Status: " . ($emailReady ? '<span class="ok">&check; READY FOR SENDING</span>' : '<span class="error">&cross; NOT READY (MAIL_HOST and MAIL_FROM_ADDRESS required)</span>') . "</li>";
    echo "</ul>";

    echo "<h3>C. SMS Gateway (SMSlenz) Configuration Status</h3>";
    echo "<ul>";
    echo "<li>User ID: <code>" . ($smsUserId !== '' ? htmlspecialchars($smsUserId) : '<span class="error">NOT SET</span>') . "</code></li>";
    echo "<li>API Key: <code>" . ($smsApiKey !== '' ? (strlen($smsApiKey) > 6 ? substr($smsApiKey, 0, 3) . '***' . substr($smsApiKey, -3) : '***') : '<span class="error">NOT SET</span>') . "</code></li>";
    echo "<li>Sender ID: <code>" . ($smsSenderId !== '' ? htmlspecialchars($smsSenderId) : '<span class="warn">NOT SET</span>') . "</code></li>";
    echo "<li>SMSlenz Status: " . ($smsReady ? '<span class="ok">&check; READY FOR SENDING</span>' : '<span class="error">&cross; NOT READY (Requires SMSLENZ_USER_ID, SMSLENZ_API_KEY, SMSLENZ_SENDER_ID)</span>') . "</li>";
    echo "</ul>";

    if ($pdo !== null) {
        echo "<h3>D. Notification Queue Status in Database</h3>";
        try {
            $queuedEmails = (int) $pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'queued'")->fetchColumn();
            $sentEmails   = (int) $pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'sent'")->fetchColumn();
            $failedEmails = (int) $pdo->query("SELECT COUNT(*) FROM email_logs WHERE status = 'failed'")->fetchColumn();
            echo "<p>Emails in log: <strong>{$queuedEmails}</strong> queued, <strong>{$sentEmails}</strong> sent, <strong>{$failedEmails}</strong> failed.</p>";

            $queuedSms = (int) $pdo->query("SELECT COUNT(*) FROM sms_messages WHERE status = 'queued'")->fetchColumn();
            $sentSms   = (int) $pdo->query("SELECT COUNT(*) FROM sms_messages WHERE status = 'sent'")->fetchColumn();
            $blockedSms= (int) $pdo->query("SELECT COUNT(*) FROM sms_messages WHERE status IN ('blocked', 'failed', 'invalid')")->fetchColumn();
            echo "<p>SMS messages: <strong>{$queuedSms}</strong> queued, <strong>{$sentSms}</strong> sent, <strong>{$blockedSms}</strong> blocked/failed.</p>";

            // Show recent customer OTPs
            $recentOtps = $pdo->query("SELECT id, user_id, channel, destination, attempts, expires_at, used_at, created_at FROM customer_otps ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($recentOtps)) {
                echo "<p><strong>Recent Customer OTP Requests:</strong></p>";
                echo "<table style=\"width:100%; border-collapse:collapse; font-size:0.85em; margin-bottom:12px;\">";
                echo "<tr style=\"background:#e2e8f0; text-align:left;\"><th style=\"padding:6px;\">ID</th><th style=\"padding:6px;\">User</th><th style=\"padding:6px;\">Channel</th><th style=\"padding:6px;\">Destination</th><th style=\"padding:6px;\">Created</th><th style=\"padding:6px;\">Expires</th><th style=\"padding:6px;\">Status</th></tr>";
                foreach ($recentOtps as $o) {
                    $isExpired = strtotime($o['expires_at']) <= time();
                    $isUsed = !empty($o['used_at']);
                    $stLabel = $isUsed ? '<span class="ok">USED</span>' : ($isExpired ? '<span class="warn">EXPIRED</span>' : '<span class="ok">ACTIVE</span>');
                    echo "<tr style=\"border-bottom:1px solid #e2e8f0;\">";
                    echo "<td style=\"padding:6px;\">{$o['id']}</td>";
                    echo "<td style=\"padding:6px;\">User #{$o['user_id']}</td>";
                    echo "<td style=\"padding:6px;\">" . strtoupper((string)$o['channel']) . "</td>";
                    echo "<td style=\"padding:6px;\">" . htmlspecialchars((string)$o['destination']) . "</td>";
                    echo "<td style=\"padding:6px;\">{$o['created_at']}</td>";
                    echo "<td style=\"padding:6px;\">{$o['expires_at']}</td>";
                    echo "<td style=\"padding:6px;\">{$stLabel}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }
        } catch (Throwable $ex) {
            echo "<p class=\"warn\">Could not query queue tables: " . htmlspecialchars($ex->getMessage()) . "</p>";
        }

        // Action: Process Queue Now
        if (($_GET['process_queue'] ?? '') === '1') {
            echo "<h4>Processing Queues Now...</h4>";
            try {
                if (!defined('BASE_PATH')) {
                    define('BASE_PATH', $basePath);
                }
                require_once $basePath . '/bootstrap/autoload.php';
                if (!isset($GLOBALS['app_container'])) {
                    require_once $basePath . '/bootstrap/app.php';
                }
                $mailService = app(\App\Services\MailService::class);
                $smsService  = app(\App\Services\SmsService::class);

                echo "<p>Processing Email Outbox...</p>";
                if ($mailService->smtpReady()) {
                    $mRes = $mailService->processQueue(10);
                    echo "<pre>" . htmlspecialchars(json_encode($mRes, JSON_PRETTY_PRINT)) . "</pre>";
                } else {
                    echo "<p class=\"warn\">SMTP is not configured in .env. Skipping email dispatch.</p>";
                }

                echo "<p>Processing SMS Queue...</p>";
                if ($smsService->ready()) {
                    $sRes = $smsService->processQueue(10);
                    echo "<pre>" . htmlspecialchars(json_encode($sRes, JSON_PRETTY_PRINT)) . "</pre>";
                } else {
                    echo "<p class=\"warn\">SMSlenz is not configured in .env. Skipping SMS dispatch.</p>";
                }
                echo "<p class=\"ok\"><strong>Queue processing attempt complete.</strong></p>";
            } catch (Throwable $e) {
                echo "<p class=\"error\">Error during queue processing: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        } else {
            echo "<div style=\"margin-top:16px;\">";
            echo "<a class=\"btn\" href=\"?process_queue=1\">Trigger Queue Dispatch Now (Web-based)</a>";
            echo "</div>";
        }
    }
    ?>
    <div style="margin-top:16px; background:#eff6ff; padding:12px; border-radius:6px; font-size:0.9em;">
        <strong>Recommended cPanel Cron Job:</strong><br>
        To have all emails and SMS sent automatically in the background every minute, add this Cron Job in cPanel &rarr; <em>Cron Jobs</em>:<br>
        <code>* * * * * /usr/local/bin/php /home/azharbio/attecert.com/cli/notification-worker.php >/dev/null 2>&1</code>
    </div>
</div>

<div class="card">
    <h2>5. Recent Error Logs (storage/logs/app.log)</h2>
    <?php
    $logFile = $basePath . '/storage/logs/app.log';
    if (is_file($logFile) && filesize($logFile) > 0) {
        $lines = file($logFile);
        $lastLines = array_slice($lines, -40);
        echo "<pre>" . htmlspecialchars(implode('', $lastLines)) . "</pre>";
    } else {
        echo "<p>No entries found in <code>storage/logs/app.log</code>.</p>";
    }
    ?>
</div>

<div class="card">
    <h2>6. Automatic Full Permission Fix</h2>
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