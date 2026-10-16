<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class SettingsService
{
    private ?array $cache = null;

    public function __construct(private Database $db)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return $all[$key] ?? $default;
    }

    public function all(): array
    {
        if ($this->cache === null) {
            $rows = $this->db->fetchAll('SELECT setting_key, setting_value FROM settings');
            $this->cache = [];
            foreach ($rows as $row) {
                $this->cache[$row['setting_key']] = $row['setting_value'];
            }
        }

        return $this->cache;
    }

    public function setMany(array $values): void
    {
        $this->db->transaction(function () use ($values): void {
        foreach ($values as $key => $value) {
            $this->db->run(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
                [$key, $value]
            );
        }
        });
        $this->cache = null;
    }
}
