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

    /** Manual updates can skip services; moving back or changing a closed case needs a reason and explicit confirmation. */
    public function change(int $caseId, string $code, ?int $staffId, ?string $notes, bool $customerVisible, bool $notify = true, bool $confirmReopenOrBackward = false): bool
    {
        return $this->transition($caseId, $code, $staffId, $notes, $customerVisible, $notify, false, $confirmReopenOrBackward);
    }

    /** Financial/process events may advance a case, but never undo later work or reopen a terminal case. */
    public function advance(int $caseId, string $code, ?int $staffId, ?string $notes, bool $customerVisible, bool $notify = true): bool
    {
        return $this->transition($caseId, $code, $staffId, $notes, $customerVisible, $notify, true, false);
    }

    private function transition(int $caseId, string $code, ?int $staffId, ?string $notes, bool $customerVisible, bool $notify, bool $automatic, bool $confirmed): bool
    {
        return $this->db->transaction(function () use ($caseId, $code, $staffId, $notes, $customerVisible, $notify, $automatic, $confirmed): bool {
            $case = $this->db->fetch(
                'SELECT c.*, cu.user_id, cu.email, cu.name AS customer_name,
                        s.code AS status_code, s.sort_order AS status_order, s.is_terminal
                 FROM cases c
                 INNER JOIN customers cu ON cu.id = c.customer_id
                 INNER JOIN workflow_statuses s ON s.id = c.status_id
                 WHERE c.id = ? AND c.deleted_at IS NULL FOR UPDATE',
                [$caseId]
            );
            if ($case === null) {
                throw new \App\Core\HttpException(404, 'Case not found.');
            }
            $target = $this->db->fetch(
                'SELECT s.*, t.name FROM workflow_statuses s
                 LEFT JOIN workflow_status_translations t ON t.status_id = s.id AND t.locale = \'en\'
                 WHERE s.code = ? AND s.is_active = 1',
                [$code]
            );
            if ($target === null) {
                throw new \RuntimeException(__('admin_workflow.active_status_required'));
            }
            if ((int) $case['status_id'] === (int) $target['id']) {
                return false;
            }
            $backward = (int) $target['sort_order'] < (int) $case['status_order'];
            if ($automatic && ((bool) $case['is_terminal'] || (int) $target['sort_order'] <= (int) $case['status_order'])) {
                return false;
            }
            if (!$automatic && ($backward || (bool) $case['is_terminal']) && (!$confirmed || trim((string) $notes) === '')) {
                throw new \RuntimeException(__('admin_workflow.reversal_required'));
            }
            $statusId = (int) $target['id'];
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
            $this->audit->log($staffId, 'case_status_changed', 'case', $caseId, [
                'from_status' => $case['status_code'], 'status' => $code,
                'source' => $automatic ? 'automatic' : 'manual',
                'confirmed_reversal' => !$automatic && ($backward || (bool) $case['is_terminal']),
            ]);
            if ($notify && $customerVisible && $case['user_id'] !== null) {
                $label = (string) ($target['name'] ?? $code);
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
                        'notes' => (string) $notes,
                        'link' => absolute_url('/portal/applications/' . $caseId),
                    ],
                    'case',
                    $caseId
                );
            }
            return true;
        });
    }

    public function history(int $caseId, bool $publicOnly = false, bool $anonymous = false): array
    {
        // Anonymous tracking exposes status and time only. Customer-visible notes can contain personal data.
        $columns = $anonymous ? 's.code, s.badge_tone, t.name, h.created_at, NULL AS notes' : 'h.*, s.code, s.badge_tone, t.name, u.name AS staff_name';
        $sql = 'SELECT ' . $columns . '
                FROM case_status_history h
                INNER JOIN workflow_statuses s ON s.id = h.status_id
                LEFT JOIN workflow_status_translations t ON t.status_id = s.id AND t.locale = ?'
                . ($anonymous ? '' : ' LEFT JOIN users u ON u.id = h.staff_id') . '
                WHERE h.case_id = ?';
        if ($publicOnly || $anonymous) {
            $sql .= ' AND h.customer_visible = 1';
        }
        $sql .= ' ORDER BY h.created_at ASC, h.id ASC';

        return $this->db->fetchAll($sql, [locale(), $caseId]);
    }
}
