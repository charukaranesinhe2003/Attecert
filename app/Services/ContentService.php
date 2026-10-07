<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class ContentService
{
    private ?array $cache = null;

    public function __construct(private Database $db)
    {
    }

    public function get(string $key, string $default = ''): string
    {
        $locale = locale();
        $blocks = $this->all();
        if (isset($blocks[$key][$locale]) && $blocks[$key][$locale] !== '') {
            return $blocks[$key][$locale];
        }

        return $blocks[$key]['en'] ?? $default;
    }

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $rows = $this->db->fetchAll(
            'SELECT b.block_key, b.group_name, t.locale, t.value
             FROM content_blocks b
             INNER JOIN content_block_translations t ON t.block_id = b.id'
        );
        $this->cache = [];
        foreach ($rows as $row) {
            $this->cache[$row['block_key']][$row['locale']] = $row['value'];
            $this->cache[$row['block_key']]['_group'] = $row['group_name'];
        }

        return $this->cache;
    }

    public function save(string $key, string $group, array $translations): void
    {
        $this->db->run(
            'INSERT INTO content_blocks (block_key, group_name) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE group_name = VALUES(group_name)',
            [$key, $group]
        );
        $id = (int) $this->db->fetchColumn('SELECT id FROM content_blocks WHERE block_key = ?', [$key]);
        foreach ($translations as $locale => $value) {
            $this->db->run(
                'INSERT INTO content_block_translations (block_id, locale, value) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)',
                [$id, $locale, $value]
            );
        }
        $this->cache = null;
    }
}
