<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;
use App\Services\AuthService;

final class CustomerController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private Database $db,
        private AuditService $audit,
        private AuthService $auth
    ) {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $sql = 'SELECT cu.*, u.is_active AS login_active FROM customers cu LEFT JOIN users u ON u.id = cu.user_id WHERE cu.deleted_at IS NULL';
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (cu.name LIKE ? OR cu.email LIKE ? OR cu.phone LIKE ? OR EXISTS (SELECT 1 FROM cases c WHERE c.customer_id = cu.id AND c.number LIKE ?))';
            $like = '%' . $q . '%';
            $params = [$like, $like, $like, $like];
        }
        $sql .= ' ORDER BY cu.created_at DESC';

        return $this->view('admin/customers/index', [
            'title' => __('admin.customers'),
            'robots' => 'noindex',
            'active' => 'customers',
            'q' => $q,
            'page' => $this->db->paginate($sql, $params, $this->pageQuery($request)),
        ], 'layouts/admin');
    }

    public function show(Request $request, string $id): Response
    {
        $customer = $this->db->fetch('SELECT cu.*, u.is_active AS login_active, u.locale FROM customers cu LEFT JOIN users u ON u.id = cu.user_id WHERE cu.id = ? AND cu.deleted_at IS NULL', [(int) $id]);
        if ($customer === null) {
            throw new HttpException(404);
        }

        return $this->view('admin/customers/show', [
            'title' => $customer['name'],
            'robots' => 'noindex',
            'active' => 'customers',
            'customer' => $customer,
            'canEdit' => $this->auth->can('customers.edit') && !$this->linkedStaff($customer),
            'cases' => $this->auth->can('cases.view') ? $this->db->fetchAll('SELECT c.*, st.name AS status_name FROM cases c LEFT JOIN workflow_status_translations st ON st.status_id = c.status_id AND st.locale = \'en\' WHERE c.customer_id = ? AND c.deleted_at IS NULL ORDER BY c.created_at DESC', [(int) $id]) : [],
            'quotations' => $this->auth->can('quotations.create') ? $this->db->fetchAll('SELECT * FROM quotations WHERE customer_id = ? ORDER BY created_at DESC', [(int) $id]) : [],
            'invoices' => $this->auth->can('invoices.create') ? $this->db->fetchAll('SELECT * FROM invoices WHERE customer_id = ? ORDER BY created_at DESC', [(int) $id]) : [],
            'payments' => $this->auth->can('payments.view') ? $this->db->fetchAll('SELECT * FROM payments WHERE customer_id = ? ORDER BY paid_at DESC', [(int) $id]) : [],
            'documents' => $this->auth->can('documents.download') ? $this->db->fetchAll('SELECT d.*, c.number FROM documents d INNER JOIN cases c ON c.id = d.case_id WHERE c.customer_id = ? AND c.deleted_at IS NULL AND d.deleted_at IS NULL ORDER BY d.created_at DESC', [(int) $id]) : [],
        ], 'layouts/admin');
    }

    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|min:2|max:160',
            'phone' => 'nullable|max:40',
            'whatsapp' => 'nullable|max:40',
            'address' => 'nullable|max:255',
            'notes' => 'nullable|max:4000',
            'is_active' => 'nullable|in:0,1',
        ]);
        $this->db->transaction(function () use ($data, $id): void {
            $customer = $this->db->fetch('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL FOR UPDATE', [(int) $id]);
            if ($customer === null) {
                throw new HttpException(404);
            }
            if ($customer['user_id']) {
                $this->db->fetch('SELECT id FROM users WHERE id = ? FOR UPDATE', [$customer['user_id']]);
            }
            if ($this->linkedStaff($customer)) {
                throw new HttpException(403, 'This customer is linked to a staff account. Manage staff access from Staff & roles.');
            }
            $active = (int) ($data['is_active'] ?? 0);
            $this->db->run(
                'UPDATE customers SET name = ?, phone = ?, whatsapp = ?, address = ?, notes = ?, is_active = ? WHERE id = ?',
                [$data['name'], $data['phone'], $data['whatsapp'], $data['address'], $data['notes'], $active, (int) $id]
            );
            if ($customer['user_id']) {
                $this->db->run('UPDATE users SET name = ?, phone = ?, is_active = ? WHERE id = ?', [$data['name'], $data['phone'], $active, $customer['user_id']]);
            }
            $this->audit->log($this->auth->id(), 'customer_updated', 'customer', (int) $id, ['previous_active' => (int) $customer['is_active'], 'active' => $active]);
        });
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/customers/' . $id);
    }

    private function linkedStaff(array $customer): bool
    {
        return !empty($customer['user_id']) && (bool) $this->db->fetchColumn('SELECT COUNT(*) FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = ? AND r.code <> ?', [$customer['user_id'], 'customer']);
    }
}
