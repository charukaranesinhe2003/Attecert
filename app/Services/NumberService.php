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

    public function nextTracking(): string
    {
        $today = new DateTimeImmutable('now', new DateTimeZone((string) config('app.timezone', 'Asia/Colombo')));
        $prefix = 'KASS/Q/' . $today->format('Ymd');
        do {
            // The unique sequence row serializes concurrent requests, including quote conversions.
            $this->db->run('INSERT INTO sequences (name, year, current_value) VALUES (?, ?, LAST_INSERT_ID(1))
                ON DUPLICATE KEY UPDATE current_value = LAST_INSERT_ID(current_value + 1)',
                ['tracking_' . $today->format('Ymd'), (int) $today->format('Y')]);
            $value = (int) $this->db->fetchColumn('SELECT LAST_INSERT_ID()');
            $number = $prefix . str_pad((string) $value, 2, '0', STR_PAD_LEFT);
            if (strlen($number) > 24) { throw new \RuntimeException('Tracking reference capacity exceeded.'); }
            // Preserve any matching imported or archived references as well.
        } while ($this->db->fetchColumn('SELECT id FROM cases WHERE number = ?', [$number]) !== false);
        return $number;
    }
}
