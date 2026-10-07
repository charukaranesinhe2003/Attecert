<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class ReportService
{
    public function __construct(private Database $db)
    {
    }

    public function dashboard(string $from, string $to): array
    {
        $params = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $counts = $this->db->fetchAll(
            'SELECT s.code, COUNT(*) AS total
             FROM cases c
             INNER JOIN workflow_statuses s ON s.id = c.status_id
             WHERE c.deleted_at IS NULL
             GROUP BY s.code'
        );
        $byStatus = [];
        foreach ($counts as $row) {
            $byStatus[$row['code']] = (int) $row['total'];
        }
        $newInRange = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM cases WHERE deleted_at IS NULL AND created_at BETWEEN ? AND ?',
            $params
        );
        $completed = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM cases WHERE deleted_at IS NULL AND completed_at BETWEEN ? AND ?',
            $params
        );
        $revenue = (string) $this->db->fetchColumn(
            'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = \'completed\' AND paid_at BETWEEN ? AND ?',
            [substr($params[0], 0, 10), substr($params[1], 0, 10)]
        );
        $outstanding = (string) $this->db->fetchColumn(
            'SELECT COALESCE(SUM(amount_due), 0) FROM invoices WHERE status IN (\'issued\', \'sent\', \'partially_paid\', \'overdue\')'
        );
        $quotes = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM quote_requests WHERE created_at BETWEEN ? AND ?',
            $params
        );

        $awaitingQuote = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM quotations WHERE status = \'draft\''
        ) + (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM cases c
             INNER JOIN workflow_statuses s ON s.id = c.status_id
             WHERE c.deleted_at IS NULL AND s.code IN (\'new_request\', \'under_review\')
               AND NOT EXISTS (SELECT 1 FROM quotations q WHERE q.case_id = c.id)'
        );
        $awaitingPayment = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM invoices WHERE status IN (\'issued\', \'sent\', \'partially_paid\', \'overdue\')'
        );
        $readyDocs = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM documents WHERE deleted_at IS NULL AND category = \'completed\''
        );

        return [
            'attention' => $this->attention(),
            'by_status' => $byStatus,
            'new_cases' => $newInRange,
            'completed' => $completed,
            'active' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM cases c INNER JOIN workflow_statuses s ON s.id = c.status_id WHERE c.deleted_at IS NULL AND s.is_terminal = 0'),
            'awaiting_quote' => $awaitingQuote,
            'awaiting_payment' => $awaitingPayment,
            'ready_docs' => $readyDocs,
            'revenue' => $revenue,
            'outstanding' => $outstanding,
            'quote_requests' => $quotes,
            'recent_quotes' => $this->db->fetchAll('SELECT * FROM quote_requests ORDER BY created_at DESC LIMIT 6'),
            'recent_documents' => $this->db->fetchAll(
                'SELECT d.id, d.original_name, d.category, d.created_at, d.case_id, c.number AS case_number, cu.name AS customer_name
                 FROM documents d
                 LEFT JOIN cases c ON c.id = d.case_id
                 LEFT JOIN customers cu ON cu.id = c.customer_id
                 WHERE d.deleted_at IS NULL
                 ORDER BY d.created_at DESC
                 LIMIT 8'
            ),
            'recent_cases' => $this->db->fetchAll(
                'SELECT c.number, c.id, c.created_at, cu.name AS customer_name, st.name AS status_name
                 FROM cases c
                 INNER JOIN customers cu ON cu.id = c.customer_id
                 INNER JOIN workflow_statuses s ON s.id = c.status_id
                 LEFT JOIN workflow_status_translations st ON st.status_id = s.id AND st.locale = \'en\'
                 WHERE c.deleted_at IS NULL
                 ORDER BY c.created_at DESC LIMIT 6'
            ),
        ];
    }

    public function attention(): array
    {
        return [
            'draft_quotes' => $this->db->fetchAll(
                'SELECT q.id, q.number, q.case_id, q.created_at, cu.name AS customer_name
                 FROM quotations q INNER JOIN customers cu ON cu.id = q.customer_id
                 WHERE q.status = \'draft\' ORDER BY q.created_at ASC LIMIT 6'
            ),
            'overdue' => $this->db->fetchAll(
                'SELECT i.id, i.number, i.case_id, i.due_at, i.amount_due, cu.name AS customer_name
                 FROM invoices i INNER JOIN customers cu ON cu.id = i.customer_id
                 WHERE i.status IN (\'issued\', \'sent\', \'partially_paid\', \'overdue\') AND i.due_at IS NOT NULL AND i.due_at < CURDATE()
                 ORDER BY i.due_at ASC LIMIT 6'
            ),
            'waiting_documents' => $this->db->fetchAll(
                'SELECT c.id, c.number, c.created_at, cu.name AS customer_name
                 FROM cases c INNER JOIN customers cu ON cu.id = c.customer_id
                 INNER JOIN workflow_statuses s ON s.id = c.status_id
                 WHERE c.deleted_at IS NULL AND s.code = \'awaiting_documents\' ORDER BY c.created_at ASC LIMIT 6'
            ),
            'mofa' => $this->db->fetchAll(
                'SELECT c.id, c.number, m.updated_at, cu.name AS customer_name
                 FROM mofa_processes m INNER JOIN cases c ON c.id = m.case_id INNER JOIN customers cu ON cu.id = c.customer_id
                 WHERE m.status IN (\'submitted\', \'processing\') ORDER BY m.expected_at ASC LIMIT 6'
            ),
            'ready' => $this->db->fetchAll(
                'SELECT d.id, d.original_name, d.case_id, c.number AS case_number, cu.name AS customer_name
                 FROM documents d INNER JOIN cases c ON c.id = d.case_id INNER JOIN customers cu ON cu.id = c.customer_id
                 WHERE d.deleted_at IS NULL AND d.category = \'completed\' AND d.customer_visible = 0
                 ORDER BY d.created_at DESC LIMIT 6'
            ),
        ];
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
                'SELECT method, COUNT(*) AS total, COALESCE(SUM(amount), 0) AS amount
                 FROM payments WHERE paid_at BETWEEN ? AND ? GROUP BY method',
                [$from, $to]
            ),
            'outstanding' => $this->db->fetchAll(
                'SELECT number, total, amount_paid, amount_due, status, due_at
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
                 FROM cases WHERE completed_at IS NOT NULL AND completed_at BETWEEN ? AND ?',
                [$start, $end]
            ),
        ];
    }
}
