<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class ReportService
{
    public function __construct(private Database $db, private AuthService $auth)
    {
    }

    public function dashboard(string $from = '', string $to = ''): array
    {
        // Operational backlogs are current across all dates; period analytics live in Reports.
        $canCases = $this->auth->can('cases.view');
        $canQuote = $canCases && $this->auth->can('quotations.create');
        $canInvoices = $this->auth->can('invoices.create');
        $canRelease = $this->auth->can('documents.download') && $this->auth->can('documents.upload');
        $attention = $this->attention();

        return [
            'attention' => $attention,
            'active' => $canCases ? (int) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM cases c INNER JOIN workflow_statuses s ON s.id = c.status_id
                 WHERE c.deleted_at IS NULL AND s.is_terminal = 0'
            ) : null,
            'unquoted' => $canQuote ? $attention['unquoted']['total'] : null,
            'awaiting_payment' => $canInvoices ? (int) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM invoices i INNER JOIN cases c ON c.id = i.case_id
                 INNER JOIN customers cu ON cu.id = i.customer_id
                 WHERE c.deleted_at IS NULL AND cu.deleted_at IS NULL
                   AND i.status IN (\'issued\', \'sent\', \'partially_paid\', \'overdue\')'
            ) : null,
            'ready_docs' => $canRelease ? $attention['ready']['total'] : null,
            'recent_quotes' => $this->auth->can('quotes.manage') ? $this->db->fetchAll(
                'SELECT id, name, status, created_at FROM quote_requests ORDER BY created_at DESC LIMIT 5'
            ) : null,
            'recent_cases' => $canCases ? $this->db->fetchAll(
                'SELECT c.number, c.id, c.created_at, cu.name AS customer_name,
                        COALESCE(st.name, s.code) AS status_name, s.badge_tone
                 FROM cases c INNER JOIN customers cu ON cu.id = c.customer_id
                 INNER JOIN workflow_statuses s ON s.id = c.status_id
                 LEFT JOIN workflow_status_translations st ON st.status_id = s.id AND st.locale = ?
                 WHERE c.deleted_at IS NULL ORDER BY c.created_at DESC, c.id DESC LIMIT 5',
                [locale()]
            ) : null,
        ];
    }

    public function attention(): array
    {
        $queues = [];
        $caseJoin = ' FROM cases c INNER JOIN customers cu ON cu.id = c.customer_id
                      INNER JOIN workflow_statuses s ON s.id = c.status_id ';
        if ($this->auth->can('cases.view') && $this->auth->can('quotations.create')) {
            $where = ' WHERE c.deleted_at IS NULL AND s.code IN (\'new_request\', \'under_review\')
                       AND NOT EXISTS (SELECT 1 FROM quotations q WHERE q.case_id = c.id)';
            $queues['unquoted'] = $this->queue(
                'SELECT c.id, c.number, c.created_at AS event_at, cu.name AS customer_name' . $caseJoin . $where . ' ORDER BY c.created_at, c.id',
                'SELECT COUNT(*)' . $caseJoin . $where
            );
        }
        if ($this->auth->can('quotations.create')) {
            $from = ' FROM quotations q INNER JOIN cases c ON c.id = q.case_id
                      INNER JOIN customers cu ON cu.id = q.customer_id
                      WHERE c.deleted_at IS NULL AND cu.deleted_at IS NULL AND q.status = \'draft\'';
            $queues['draft_quotes'] = $this->queue(
                'SELECT q.id, q.number, q.case_id, c.number AS case_number, q.created_at AS event_at, cu.name AS customer_name' . $from . ' ORDER BY q.created_at, q.id',
                'SELECT COUNT(*)' . $from
            );
        }
        if ($this->auth->can('invoices.create')) {
            $from = ' FROM invoices i INNER JOIN cases c ON c.id = i.case_id
                      INNER JOIN customers cu ON cu.id = i.customer_id
                      WHERE c.deleted_at IS NULL AND cu.deleted_at IS NULL
                        AND i.status IN (\'issued\', \'sent\', \'partially_paid\', \'overdue\')
                        AND i.due_at IS NOT NULL AND i.due_at < CURDATE()';
            $queues['overdue'] = $this->queue(
                'SELECT i.id, i.number, i.case_id, c.number AS case_number, i.due_at AS event_at, cu.name AS customer_name' . $from . ' ORDER BY i.due_at, i.id',
                'SELECT COUNT(*)' . $from
            );
        }
        if ($this->auth->can('cases.view')) {
            $where = ' WHERE c.deleted_at IS NULL AND s.code = \'awaiting_documents\'';
            $queues['waiting_documents'] = $this->queue(
                'SELECT c.id, c.number, c.created_at AS event_at, cu.name AS customer_name' . $caseJoin . $where . ' ORDER BY c.created_at, c.id',
                'SELECT COUNT(*)' . $caseJoin . $where
            );
        }
        if ($this->auth->can('cases.view') && $this->auth->can('cases.edit')) {
            foreach (['mofa' => 'mofa_processes', 'embassy' => 'embassy_processes'] as $key => $table) {
                // Each save appends a snapshot. Only the newest record represents current work.
                $from = ' FROM ' . $table . ' p INNER JOIN cases c ON c.id = p.case_id
                          INNER JOIN customers cu ON cu.id = c.customer_id
                          INNER JOIN workflow_statuses s ON s.id = c.status_id
                          WHERE c.deleted_at IS NULL AND s.is_terminal = 0
                            AND p.id = (SELECT MAX(latest.id) FROM ' . $table . ' latest WHERE latest.case_id = p.case_id)
                            AND p.status IN (\'submitted\', \'processing\')';
                $queues[$key] = $this->queue(
                    'SELECT c.id, c.number, p.updated_at AS event_at, p.expected_at, cu.name AS customer_name' . $from . ' ORDER BY p.expected_at IS NULL, p.expected_at, p.updated_at, p.id',
                    'SELECT COUNT(*)' . $from
                );
            }
        }
        if ($this->auth->can('documents.download') && $this->auth->can('documents.upload')) {
            $from = ' FROM documents d INNER JOIN cases c ON c.id = d.case_id
                      INNER JOIN customers cu ON cu.id = c.customer_id
                      WHERE d.deleted_at IS NULL AND c.deleted_at IS NULL AND cu.deleted_at IS NULL
                        AND d.category = \'completed\' AND d.customer_visible = 0';
            $queues['ready'] = $this->queue(
                'SELECT d.id, d.original_name, d.case_id, c.number AS case_number, d.created_at AS event_at, cu.name AS customer_name' . $from . ' ORDER BY d.created_at, d.id',
                'SELECT COUNT(*)' . $from
            );
        }

        return $queues;
    }

    private function queue(string $rowsSql, string $countSql): array
    {
        $total = (int) $this->db->fetchColumn($countSql);

        return ['total' => $total, 'rows' => $total > 0 ? $this->db->fetchAll($rowsSql . ' LIMIT 5') : []];
    }

    public function summary(string $from, string $to): array
    {
        $start = $from . ' 00:00:00';
        $end = $to . ' 23:59:59';

        return [
            'by_status' => $this->db->fetchAll(
                'SELECT s.code, COALESCE(t.name, s.code) AS name, COUNT(*) AS total
                 FROM cases c
                 INNER JOIN workflow_statuses s ON s.id = c.status_id
                 LEFT JOIN workflow_status_translations t ON t.status_id = s.id AND t.locale = \'en\'
                 WHERE c.deleted_at IS NULL AND c.created_at BETWEEN ? AND ?
                 GROUP BY s.id, s.code, t.name
                 ORDER BY total DESC',
                [$start, $end]
            ),
            'by_country' => $this->db->fetchAll(
                'SELECT COALESCE(ct.name, \'Unspecified\') AS name, COUNT(*) AS total
                 FROM cases c
                 LEFT JOIN country_translations ct ON ct.country_id = c.country_id AND ct.locale = \'en\'
                 WHERE c.deleted_at IS NULL AND c.created_at BETWEEN ? AND ?
                 GROUP BY ct.name
                 ORDER BY total DESC',
                [$start, $end]
            ),
            'by_service' => $this->db->fetchAll(
                'SELECT COALESCE(st.name, s.code) AS name, COUNT(*) AS total
                 FROM case_services cs
                 INNER JOIN cases c ON c.id = cs.case_id
                 INNER JOIN services s ON s.id = cs.service_id
                 LEFT JOIN service_translations st ON st.service_id = s.id AND st.locale = \'en\'
                 WHERE c.deleted_at IS NULL AND c.created_at BETWEEN ? AND ?
                 GROUP BY s.id, st.name, s.code
                 ORDER BY total DESC',
                [$start, $end]
            ),
            'payments' => $this->db->fetchAll(
                'SELECT method, currency, COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount
                 FROM payments WHERE status IN (\'completed\', \'refunded\') AND paid_at BETWEEN ? AND ? GROUP BY method, currency',
                [$from, $to]
            ),
            'outstanding' => $this->db->fetchAll(
                'SELECT id, number, currency, total, amount_paid, amount_due, status, due_at
                 FROM invoices WHERE status IN (\'issued\', \'sent\', \'partially_paid\', \'overdue\')
                 ORDER BY due_at'
            ),
            'quotations' => $this->db->fetch(
                'SELECT COUNT(*) AS total,
                        SUM(status IN (\'accepted\', \'converted\')) AS accepted
                 FROM quotations WHERE created_at BETWEEN ? AND ?',
                [$start, $end]
            ),
            'customers' => (int) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL AND created_at BETWEEN ? AND ?',
                [$start, $end]
            ),
            'turnaround' => $this->db->fetchColumn(
                'SELECT ROUND(AVG(TIMESTAMPDIFF(DAY, created_at, completed_at)), 1)
                 FROM cases WHERE deleted_at IS NULL AND completed_at IS NOT NULL AND completed_at BETWEEN ? AND ?',
                [$start, $end]
            ),
        ];
    }
}
