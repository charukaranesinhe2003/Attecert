<?php
require_once __DIR__ . '/../bootstrap/autoload.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    email TEXT,
    phone TEXT,
    password_hash TEXT,
    locale TEXT,
    email_verified_at TEXT,
    phone_verified_at TEXT,
    verified_phone TEXT,
    otp_verified_at TEXT,
    otp_verified_channel TEXT,
    otp_verified_destination TEXT,
    is_active INTEGER DEFAULT 1,
    deleted_at TEXT,
    failed_login_count INTEGER DEFAULT 0,
    last_login_at TEXT,
    last_login_ip TEXT
)");

$pdo->exec("CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, code TEXT, name TEXT)");
$pdo->exec("INSERT INTO roles (code, name) VALUES ('customer', 'Customer'), ('admin', 'Admin')");
$pdo->exec("CREATE TABLE user_roles (user_id INTEGER, role_id INTEGER)");
$pdo->exec("CREATE TABLE customers (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, name TEXT, email TEXT, phone TEXT, deleted_at TEXT)");
$pdo->exec("CREATE TABLE settings (`key` TEXT PRIMARY KEY, `value` TEXT)");
$pdo->exec("CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, action TEXT, entity TEXT, entity_id INTEGER, metadata TEXT, ip TEXT, user_agent TEXT, created_at TEXT)");
$pdo->exec("CREATE TABLE customer_otps (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, channel TEXT, destination TEXT, code_hash TEXT, password_fingerprint TEXT, attempts INTEGER DEFAULT 0, expires_at TEXT, used_at TEXT, created_at TEXT)");

// Inject database
$dbReflection = new ReflectionClass(\App\Core\Database::class);
$db = $dbReflection->newInstanceWithoutConstructor();
$pdoProp = $dbReflection->getProperty('pdo');
$pdoProp->setAccessible(true);
$pdoProp->setValue($db, $pdo);

$container = new \App\Core\Container();
$container->instance(\App\Core\Database::class, $db);
$GLOBALS['app_container'] = $container;

\App\Core\Config::load(__DIR__ . '/../config');

$auth = $container->get(\App\Services\AuthService::class);

echo "Registering user...\n";
$userId = $auth->register('Test User', 'test@example.com', '+94712345678', 'Secret1234!', 'en');

$userRow = $pdo->query("SELECT * FROM users WHERE id = $userId")->fetch(PDO::FETCH_ASSOC);
echo "User registered row:\n";
print_r($userRow);

$_SESSION['user_id'] = $userId;
echo "\nChecking needsVerification():\n";
var_dump($auth->needsVerification());

echo "Checking homePath():\n";
echo $auth->homePath() . PHP_EOL;
