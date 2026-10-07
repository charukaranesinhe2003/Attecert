<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\CaseService;
use App\Services\NotificationService;

final class DashboardController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private AuthService $auth,
        private CaseService $cases,
        private Database $db,
        private NotificationService $notifications
    ) {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $customer = $this->auth->customer();
        $id = (int) $customer['id'];

        return $this->view('customer/dashboard', [
            'title' => __('portal.dashboard'),
            'robots' => 'noindex',
            'active' => 'dashboard',
            'customer' => $customer,
            'cases' => array_slice($this->cases->forCustomerList($id), 0, 5),
            'quotations' => $this->db->fetchAll('SELECT * FROM quotations WHERE customer_id = ? AND status <> \'draft\' ORDER BY created_at DESC LIMIT 5', [$id]),
            'invoices' => $this->db->fetchAll('SELECT * FROM invoices WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5', [$id]),
            'documents' => (int) $this->db->fetchColumn('SELECT COUNT(*) FROM documents d INNER JOIN cases c ON c.id = d.case_id WHERE c.customer_id = ? AND d.customer_visible = 1 AND d.deleted_at IS NULL', [$id]),
            'unread' => $this->notifications->unreadCount((int) $this->auth->id()),
        ], 'layouts/customer');
    }
}
