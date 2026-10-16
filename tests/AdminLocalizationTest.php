<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class AdminLocalizationTest extends TestCase
{
    public function testSinhalaAndTamilCoverAdminKeysAndPreservePlaceholders(): void
    {
        $core = require base_path('resources/lang/en.php');
        $english = array_replace_recursive(
            array_intersect_key($core, array_fill_keys(['admin', 'admin_overview', 'fields', 'actions'], true)),
            require base_path('resources/lang/admin/en.php')
        );
        $expected = $this->flatten($english);
        foreach (['si' => '/[\x{0D80}-\x{0DFF}]/u', 'ta' => '/[\x{0B80}-\x{0BFF}]/u'] as $locale => $script) {
            // Inspect the locale itself, so English fallback cannot conceal a missing translation.
            $actual = $this->flatten(require base_path('resources/lang/admin/' . $locale . '.php'));
            foreach ($expected as $key => $text) {
                self::assertArrayHasKey($key, $actual, $locale . ' is missing ' . $key);
                self::assertNotSame('', trim($actual[$key]), $locale . ' has an empty ' . $key);
                self::assertSame(1, preg_match('//u', $actual[$key]), $locale . ' has invalid UTF-8 in ' . $key);
                self::assertSame($this->placeholders($text), $this->placeholders($actual[$key]), $locale . ' changed placeholders in ' . $key);
            }
            foreach (array_keys($english) as $namespace) {
                self::assertMatchesRegularExpression($script, implode(' ', array_intersect_key($actual, array_filter($expected, static fn (string $key): bool => str_starts_with($key, $namespace . '.'), ARRAY_FILTER_USE_KEY))), $locale . ' has no localized text in ' . $namespace);
            }
        }
    }

    private function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            $key = $prefix === '' ? $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flat += $this->flatten($value, $key);
            } else {
                $flat[$key] = $value;
            }
        }
        return $flat;
    }

    private function placeholders(string $value): array
    {
        preg_match_all('/:[a-z][a-z0-9_]*/i', $value, $matches);
        $tokens = $matches[0];
        sort($tokens);
        return $tokens;
    }
}
