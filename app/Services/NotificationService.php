<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class NotificationService
{
    public function __construct(private Database $db)
    {
    }

    public function notify(?int $userId, string $title, string $body, ?string $link = null): void
    {
        if ($userId === null) {
            return;
        }
        $this->db->insert(
            'INSERT INTO notifications (user_id, title, body, link) VALUES (?, ?, ?, ?)',
            [$userId, $title, $body, $link]
        );
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL',
            [$userId]
        );
    }

    public function forUser(int $userId, int $limit = 30): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ' . (int) $limit,
            [$userId]
        );
    }

    public function markRead(int $id, int $userId): void
    {
        $this->db->run(
            'UPDATE notifications SET read_at = NOW() WHERE id = ? AND user_id = ? AND read_at IS NULL',
            [$id, $userId]
        );
    }

    public function markAll(int $userId): void
    {
        $this->db->run('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL', [$userId]);
    }
}
