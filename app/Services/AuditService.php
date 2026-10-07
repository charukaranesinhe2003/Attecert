<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class AuditService
{
    public function __construct(private Database $db)
    {
    }

    public function log(?int $userId, string $action, ?string $entity = null, ?int $entityId = null, array $metadata = []): void
    {
        $this->db->insert(
            'INSERT INTO audit_logs (user_id, action, entity, entity_id, metadata, ip, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $action,
                $entity,
                $entityId,
                $metadata === [] ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE),
                client_ip(),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'cli'), 0, 255),
            ]
        );
    }
}
