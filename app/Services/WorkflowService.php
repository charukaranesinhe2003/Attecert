<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class WorkflowService
{
    public function __construct(
        private Database $db,
        private AuditService $audit,
        private NotificationService $notifications,
        private MailService $mail
    ) {
    }

    public function statuses(bool $activeOnly = true): array
    {
        $sql = 'SELECT s.*, t.name, t.description
                FROM workflow_statuses s
                LEFT JOIN workflow_status_translations t ON t.status_id = s.id AND t.locale = ?
                WHERE 1 = 1';
        if ($activeOnly) {
            $sql .= ' AND s.is_active = 1';
        }
        $sql .= ' ORDER BY s.sort_order';
        $rows = $this->db->fetchAll($sql, [locale()]);
        if ($rows !== [] && ($rows[0]['name'] ?? null) === null) {
            return $this->db->fetchAll(
                'SELECT s.*, t.name, t.description
                 FROM workflow_statuses s
                 LEFT JOIN workflow_status_translations t ON t.status_id = s.id AND t.locale = \'en\'
                 ' . ($activeOnly ? 'WHERE s.is_active = 1' : '') . '
                 ORDER BY s.sort_order'
            );
        }

        return $rows;
    }

    public function idFor(string $code): int
    {
        $id = $this->db->fetchColumn('SELECT id FROM workflow_statuses WHERE code = ?', [$code]);
        if ($id === false || $id === null) {
            throw new \RuntimeException('Unknown status: ' . $code);
        }

        return (int) $id;
    }

    public function change(int $caseId, string $code, ?int $staffId, ?string $notes, bool $customerVisible, bool $notify = true): void
    {
        $statusId = $this->idFor($code);
        $case = $this->db->fetch(
            'SELECT c.*, cu.user_id, cu.email, cu.name AS customer_name, t.name AS status_name
             FROM cases c
             INNER JOIN customers cu ON cu.id = c.customer_id
             INNER JOIN workflow_statuses s ON s.id = ?
             LEFT JOIN workflow_status_translations t ON t.status_id = s.id AND t.locale = \'en\'
             WHERE c.id = ?',
            [$statusId, $caseId]
        );
        if ($case === null) {
            throw new \RuntimeException('Case not found.');
        }
        $this->db->transaction(function () use ($caseId, $statusId, $code, $staffId, $notes, $customerVisible): void {
            $this->db->run('UPDATE cases SET status_id = ?, completed_at = ?, cancelled_at = ? WHERE id = ?', [
                $statusId,
                $code === 'completed' ? date('Y-m-d H:i:s') : null,
                $code === 'cancelled' ? date('Y-m-d H:i:s') : null,
                $caseId,
            ]);
            $this->db->insert(
                'INSERT INTO case_status_history (case_id, status_id, staff_id, notes, customer_visible) VALUES (?, ?, ?, ?, ?)',
                [$caseId, $statusId, $staffId, $notes, $customerVisible ? 1 : 0]
            );
        });
        $this->audit->log($staffId, 'case_status_changed', 'case', $caseId, ['status' => $code]);
        if ($notify && $customerVisible && $case['user_id'] !== null) {
            $label = (string) ($case['status_name'] ?? $code);
            $this->notifications->notify(
                (int) $case['user_id'],
                __('notifications.status_title', ['status' => $label]),
                __('notifications.status_body', ['number' => $case['number'], 'status' => $label]),
                '/portal/applications/' . $caseId
            );
            $this->mail->send(
                $case['email'],
                __('mail.status_subject', ['number' => $case['number']]),
                'status-changed',
                [
                    'name' => $case['customer_name'],
                    'number' => $case['number'],
                    'status' => $label,
                    'notes' => $customerVisible ? (string) $notes : '',
                    'link' => absolute_url('/portal/applications/' . $caseId),
                ],
                'case',
                $caseId
            );
        }
    }

    public function history(int $caseId, bool $publicOnly = false): array
    {
        $sql = 'SELECT h.*, s.code, s.badge_tone, t.name, u.name AS staff_name
                FROM case_status_history h
                INNER JOIN workflow_statuses s ON s.id = h.status_id
                LEFT JOIN workflow_status_translations t ON t.status_id = s.id AND t.locale = ?
                LEFT JOIN users u ON u.id = h.staff_id
                WHERE h.case_id = ?';
        if ($publicOnly) {
            $sql .= ' AND h.customer_visible = 1';
        }
        $sql .= ' ORDER BY h.created_at ASC, h.id ASC';

        return $this->db->fetchAll($sql, [locale(), $caseId]);
    }
}
