<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class DashboardController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private AuthService $auth,
        private Database $db
    ) {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $customer = $this->auth->customer();
        $user = $this->auth->user();
        $id = (int) $customer['id'];
        $locale = locale();
        $cases = $this->db->fetchAll(
            'SELECT c.*, ct.name AS country_name, co.code AS country_code, st.name AS status_name, s.code AS status_code, s.badge_tone, s.is_terminal,
                    (SELECT t.name FROM case_services cs INNER JOIN service_translations t ON t.service_id = cs.service_id AND t.locale = ? WHERE cs.case_id = c.id ORDER BY cs.id LIMIT 1) AS service_name
             FROM cases c
             LEFT JOIN countries co ON co.id = c.country_id
             LEFT JOIN country_translations ct ON ct.country_id = c.country_id AND ct.locale = ?
             INNER JOIN workflow_statuses s ON s.id = c.status_id
             LEFT JOIN workflow_status_translations st ON st.status_id = s.id AND st.locale = ?
             WHERE c.customer_id = ? AND c.deleted_at IS NULL
             ORDER BY c.created_at DESC',
            [$locale, $locale, $locale, $id]
        );
        $quotations = $this->db->fetchAll(
            'SELECT q.*, (SELECT description FROM quotation_items WHERE quotation_id = q.id ORDER BY id LIMIT 1) AS item_name
             FROM quotations q WHERE q.customer_id = ? AND q.status <> \'draft\' ORDER BY q.created_at DESC LIMIT 6',
            [$id]
        );
        $invoices = $this->db->fetchAll(
            'SELECT i.*, (SELECT description FROM invoice_items WHERE invoice_id = i.id ORDER BY id LIMIT 1) AS item_name
             FROM invoices i WHERE i.customer_id = ? ORDER BY i.created_at DESC LIMIT 6',
            [$id]
        );
        $payments = $this->db->fetchAll(
            'SELECT p.*, i.number AS invoice_number FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE p.customer_id = ? ORDER BY p.paid_at DESC, p.id DESC LIMIT 6',
            [$id]
        );
        $documents = $this->db->fetchAll(
            'SELECT d.*, c.number AS case_number FROM documents d INNER JOIN cases c ON c.id = d.case_id
             WHERE c.customer_id = ? AND d.customer_visible = 1 AND d.deleted_at IS NULL ORDER BY d.created_at DESC LIMIT 6',
            [$id]
        );
        $active = 0;
        foreach ($cases as $case) {
            if ((int) $case['is_terminal'] === 0) {
                $active++;
            }
        }

        return $this->view('customer/dashboard', [
            'title' => __('portal.my_account'),
            'description' => __('portal.welcome_lead'),
            'robots' => 'noindex',
            'active' => 'account',
            'customer' => $customer,
            'user' => $user,
            'cases' => $cases,
            'quotations' => $quotations,
            'invoices' => $invoices,
            'payments' => $payments,
            'documents' => $documents,
            'counts' => [
                'applications' => count($cases),
                'tracking' => $active,
                'quotations' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM quotations WHERE customer_id = ? AND status <> \'draft\'', [$id]),
                'invoices' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM invoices WHERE customer_id = ?', [$id]),
                'payments' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM payments WHERE customer_id = ?', [$id]),
                'documents' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM documents d INNER JOIN cases c ON c.id = d.case_id WHERE c.customer_id = ? AND d.customer_visible = 1 AND d.deleted_at IS NULL', [$id]),
            ],
        ]);
    }
}
