<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Config;
use App\Core\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class MergeRoutingTest extends TestCase
{
    private array $originalConfig;
    private array $originalGlobals;

    protected function setUp(): void
    {
        $property = new ReflectionProperty(Config::class, 'items');
        $this->originalConfig = $property->getValue();
        $property->setValue(null, ['app' => ['url' => 'https://attecert.example', 'default_locale' => 'en', 'locales' => ['en' => 'English', 'si' => 'Sinhala']]]);
        foreach (['_SERVER', '_GET', '_POST', '_FILES', '_COOKIE', '_SESSION'] as $name) {
            $this->originalGlobals[$name] = $GLOBALS[$name] ?? null;
            $GLOBALS[$name] = [];
        }
        $_SESSION['locale'] = 'en';
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Config::class, 'items'))->setValue(null, $this->originalConfig);
        foreach ($this->originalGlobals as $name => $value) {
            if ($value === null) { unset($GLOBALS[$name]); } else { $GLOBALS[$name] = $value; }
        }
    }

    public static function installations(): array
    {
        return [
            'public document root' => ['/index.php', '/admin/cases?status=active', '', '/admin/cases'],
            'root home rewrite' => ['/public/index.php', '/', '', '/'],
            'root route rewrite' => ['/public/index.php', '/admin/cases', '', '/admin/cases'],
            'explicit public root' => ['/public/index.php', '/public/admin/cases', '/public', '/admin/cases'],
            'subfolder wrapper' => ['/Attecert/index.php', '/Attecert/admin/cases', '/Attecert', '/admin/cases'],
            'subfolder home rewrite' => ['/Attecert/public/index.php', '/Attecert/', '/Attecert', '/'],
            'subfolder route rewrite' => ['/Attecert/public/index.php', '/Attecert/admin/cases', '/Attecert', '/admin/cases'],
            'explicit subfolder public' => ['/Attecert/public/index.php', '/Attecert/public/admin/cases', '/Attecert/public', '/admin/cases'],
            'public prefix is a segment' => ['/public/index.php', '/publication/admin', '', '/publication/admin'],
            'subfolder public prefix is a segment' => ['/Attecert/public/index.php', '/Attecert/publicity/admin', '/Attecert', '/publicity/admin'],
            'sibling folder is not stripped' => ['/Attecert/public/index.php', '/Attecert-other/admin', '/Attecert/public', '/Attecert-other/admin'],
        ];
    }

    #[DataProvider('installations')]
    public function testHelpersAndCapturedRoutesAgree(string $script, string $uri, string $base, string $route): void
    {
        $_SERVER = ['SCRIPT_NAME' => $script, 'REQUEST_URI' => $uri, 'REQUEST_METHOD' => 'GET'];
        self::assertSame($base, web_base());
        self::assertSame($base, base_url_path());
        self::assertSame($route, Request::capture()->path());
        self::assertSame($base . '/admin/cases', url('/admin/cases/'));
        self::assertSame($base . '/', url('/'));
        self::assertStringStartsWith($base . '/assets/css/admin.css?v=', asset('css/admin.css'));
        self::assertSame('https://attecert.example' . $base . '/admin/cases', absolute_url('/admin/cases'));
    }

    public function testConfiguredAbsoluteBaseIsPreservedForMailAndLocalizedLinks(): void
    {
        $property = new ReflectionProperty(Config::class, 'items');
        $items = $property->getValue();
        $items['app']['url'] = 'https://attecert.example:8443/Attecert';
        $property->setValue(null, $items);
        $_SERVER = ['SCRIPT_NAME' => '/Attecert/public/index.php', 'REQUEST_URI' => '/Attecert/admin'];
        $_SESSION['locale'] = 'si';
        self::assertSame('/Attecert/admin/cases?status=active&lang=si', url('/admin/cases?status=active'));
        self::assertSame('https://attecert.example:8443/Attecert/admin/cases?status=active&lang=si', absolute_url('/admin/cases?status=active'));
        self::assertSame('https://external.example/document', absolute_url('https://external.example/document'));
        unset($_SERVER['REQUEST_URI']);
        $_SERVER['SCRIPT_NAME'] = 'cli/mail-worker.php';
        self::assertSame('', base_url_path());
        self::assertSame('https://attecert.example:8443/Attecert/portal/invoices/123?lang=si', absolute_url('/portal/invoices/123'));
    }
}
