<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;

/** Snapshots contain public progress only. Files, payment values and private messages stay outside this history. */
final class ProgressHistoryService
{
    public function __construct(private Database $db) {}

    public function record(int $caseId, string $title, ?string $description, string $state, ?string $stepDate = null,
        ?int $stepId = null, ?int $statusHistoryId = null, string $eventType = 'step_updated'): void
    {
        $this->db->insert('INSERT INTO case_progress_history (case_id, step_id, status_history_id, event_type, title, public_description, state, step_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [$caseId, $stepId, $statusHistoryId, $eventType, $title, $description, $state, $stepDate]);
    }

    public function forCase(int $caseId): array
    {
        return $this->db->fetchAll("SELECT title, public_description, state, step_date, event_type, created_at FROM (
            SELECT id AS sort_id, title, public_description, state, step_date, event_type, created_at
            FROM case_progress_history WHERE case_id = ?
            UNION ALL
            SELECT h.id AS sort_id, COALESCE(h.custom_status, t.name, s.code), NULL, 'status', DATE(h.created_at), 'status_updated', h.created_at
            FROM case_status_history h INNER JOIN workflow_statuses s ON s.id = h.status_id
            LEFT JOIN workflow_status_translations t ON t.status_id = s.id AND t.locale = 'en'
            LEFT JOIN case_progress_history p ON p.status_history_id = h.id
            WHERE h.case_id = ? AND h.customer_visible = 1 AND p.id IS NULL
            ) AS progress ORDER BY created_at, sort_id", [$caseId, $caseId]);
    }
}
