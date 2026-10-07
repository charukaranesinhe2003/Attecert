<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;

final class NumberService
{
    public function __construct(private Database $db)
    {
    }

    public function next(string $sequence, string $prefix): string
    {
        $zone = new DateTimeZone((string) config('app.timezone', 'Asia/Colombo'));
        $year = (int) (new DateTimeImmutable('now', $zone))->format('Y');
        $this->db->run(
            'INSERT INTO sequences (name, year, current_value) VALUES (?, ?, LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE current_value = LAST_INSERT_ID(current_value + 1)',
            [$sequence, $year]
        );
        $value = (int) $this->db->fetchColumn('SELECT LAST_INSERT_ID()');

        return sprintf('%s-%d-%06d', $prefix, $year, $value);
    }
}
