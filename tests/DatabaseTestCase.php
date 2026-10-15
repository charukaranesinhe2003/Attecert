<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Config;
use App\Core\Container;
use App\Core\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/** Opt-in integration fixture: services never use the configured application database. */
abstract class DatabaseTestCase extends TestCase
{
    private static ?PDO $server = null;
    private static ?Database $fixture = null;
    private static string $databaseName = '';
    private static string $artifactRoot = '';
    protected Database $db;
    protected int $adminId;
    protected int $customerId;
    protected int $caseId;

    public static function setUpBeforeClass(): void
    {
        if (getenv('ATTECERT_INTEGRATION') !== '1') {
            return;
        }
        load_env(base_path('.env'));
        Config::load(base_path('config'));
        date_default_timezone_set((string) config('app.timezone'));
        $settings = config('database');
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $settings['host'], $settings['port']);
        self::$server = new PDO($dsn, $settings['username'], $settings['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        self::$databaseName = 'attecert_test_' . bin2hex(random_bytes(8));
        if (self::$databaseName === $settings['database']) {
            throw new \RuntimeException('Refusing to use the configured application database.');
        }
        self::$artifactRoot = base_path('storage/cache/' . self::$databaseName);
        self::configureFixture();
        self::$server->exec('CREATE DATABASE `' . self::$databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        try {
            self::$fixture = new Database();
            foreach (glob(base_path('database/migrations/*.sql')) ?: [] as $migration) {
                self::$fixture->pdo()->exec((string) file_get_contents($migration));
            }
            $container = new Container();
            $container->instance(Database::class, self::$fixture);
            $GLOBALS['app_container'] = $container;
            require_once base_path('database/seeders/DatabaseSeeder.php');
            ob_start();
            try {
                (new \Database\Seeders\DatabaseSeeder(self::$fixture))->run();
            } finally {
                ob_end_clean();
            }
        } catch (\Throwable $exception) {
            self::tearDownAfterClass();
            throw $exception;
        }
    }

    protected function setUp(): void
    {
        if (getenv('ATTECERT_INTEGRATION') !== '1') {
            $this->markTestSkipped('Set ATTECERT_INTEGRATION=1 to use a separate disposable MySQL database.');
        }
        self::configureFixture();
        $this->db = self::$fixture;
        $this->resetContainer();
        $_SESSION = ['locale' => 'en'];
        $_GET = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['SCRIPT_NAME'] = '/index.php';
        $_SERVER['REQUEST_URI'] = '/admin';
        $this->adminId = (int) $this->db->fetchColumn('SELECT id FROM users WHERE email = ?', ['admin@attecert.local']);
        $this->customerId = (int) $this->db->fetchColumn('SELECT id FROM customers ORDER BY id LIMIT 1');
        $this->caseId = (int) $this->db->fetchColumn('SELECT id FROM cases ORDER BY id LIMIT 1');
        $_SESSION['user_id'] = $this->adminId;
        $this->db->pdo()->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->db) && $this->db->pdo()->inTransaction()) {
            $this->db->pdo()->rollBack();
        }
        $_SESSION = [];
        $_GET = [];
    }

    protected function resetContainer(): void
    {
        $container = new Container();
        $container->instance(Database::class, self::$fixture);
        $GLOBALS['app_container'] = $container;
    }

    protected function signInAsRole(string $role): int
    {
        $id = $this->db->insert('INSERT INTO users (name, email, password_hash, email_verified_at) VALUES (?, ?, ?, NOW())', [
            'Test ' . $role, bin2hex(random_bytes(6)) . '@example.test', password_hash('Fixture-Only-123!', PASSWORD_DEFAULT),
        ]);
        $roleId = (int) $this->db->fetchColumn('SELECT id FROM roles WHERE code = ?', [$role]);
        $this->db->run('INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)', [$id, $roleId]);
        $_SESSION['user_id'] = $id;
        $this->resetContainer();

        return $id;
    }

    private static function configureFixture(): void
    {
        // Config has no mutation API. Keep these overrides in the test harness.
        $property = new ReflectionProperty(Config::class, 'items');
        $items = $property->getValue();
        $items['database']['database'] = self::$databaseName;
        $items['app']['storage_path'] = self::$artifactRoot;
        $items['mail']['host'] = ''; // Never send external messages from tests.
        $property->setValue(null, $items);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null && preg_match('/^attecert_test_[a-f0-9]{16}$/', self::$databaseName)) {
            self::$fixture = null;
            self::$server->exec('DROP DATABASE IF EXISTS `' . self::$databaseName . '`');
            self::$server = null;
        }
        // Restrict cleanup to this run's verified, randomly named artifact directory.
        $root = realpath(self::$artifactRoot);
        $cache = realpath(base_path('storage/cache'));
        if ($root !== false && $cache !== false && str_starts_with($root, $cache . DIRECTORY_SEPARATOR)
            && basename($root) === self::$databaseName) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($root);
        }
    }
}
