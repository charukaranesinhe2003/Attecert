<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class TranslationService
{
    private array $files = [];
    private array $overrides = [];

    public function __construct(private Database $db)
    {
    }

    public function get(string $key, array $replace = []): string
    {
        $locale = locale();
        $value = $this->override($locale, $key)
            ?? $this->fileValue($locale, $key)
            ?? $this->fileValue('en', $key)
            ?? $key;
        foreach ($replace as $name => $replacement) {
            $value = str_replace(':' . $name, (string) $replacement, $value);
        }

        return $value;
    }

    public function set(string $locale, string $key, string $value): void
    {
        $this->db->run(
            'INSERT INTO ui_translations (locale, trans_key, value) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$locale, $key, $value]
        );
        unset($this->overrides[$locale]);
    }

    public function fileKeys(): array
    {
        $file = $this->loadFile('en');

        return $this->flatten($file);
    }

    private function override(string $locale, string $key): ?string
    {
        if (!isset($this->overrides[$locale])) {
            $rows = $this->db->fetchAll('SELECT trans_key, value FROM ui_translations WHERE locale = ?', [$locale]);
            $this->overrides[$locale] = [];
            foreach ($rows as $row) {
                $this->overrides[$locale][$row['trans_key']] = $row['value'];
            }
        }

        return $this->overrides[$locale][$key] ?? null;
    }

    private function fileValue(string $locale, string $key): ?string
    {
        $data = $this->loadFile($locale);
        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }
            $data = $data[$segment];
        }

        return is_string($data) ? $data : null;
    }

    private function loadFile(string $locale): array
    {
        if (!isset($this->files[$locale])) {
            $path = base_path('resources/lang/' . $locale . '.php');
            $this->files[$locale] = is_file($path) ? require $path : [];
        }

        return $this->files[$locale];
    }

    private function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];
        foreach ($data as $key => $value) {
            $full = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $flat += $this->flatten($value, $full);
            } else {
                $flat[$full] = (string) $value;
            }
        }

        return $flat;
    }
}
