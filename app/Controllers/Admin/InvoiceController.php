<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\InvoiceService;

final class InvoiceController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private Database $db,
        private InvoiceService $invoices,
        private AuthService $auth
    ) {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $sql = 'SELECT i.*, c.number AS case_number, cu.name AS customer_name FROM invoices i INNER JOIN cases c ON c.id = i.case_id INNER JOIN customers cu ON cu.id = i.customer_id WHERE 1 = 1';
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (i.number LIKE ? OR c.number LIKE ? OR cu.name LIKE ?)';
            $like = '%' . $q . '%';
            $params = [$like, $like, $like];
        }
        if ($status !== '') {
            $sql .= ' AND i.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY i.created_at DESC';

        return $this->view('admin/invoices/index', [
            'title' => __('admin.invoices'),
            'robots' => 'noindex',
            'active' => 'invoices',
            'q' => $q,
            'status' => $status,
            'page' => $this->db->paginate($sql, $params, $this->pageQuery($request)),
        ], 'layouts/admin');
    }

    public function show(Request $request, string $id): Response
    {
        return $this->view('admin/invoices/show', [
            'title' => __('admin.invoices'),
            'robots' => 'noindex',
            'active' => 'invoices',
            'bundle' => $this->invoices->bundle((int) $id),
        ], 'layouts/admin');
    }

    public function pdf(Request $request, string $id): Response
    {
        $bundle = $this->invoices->bundle((int) $id);

        return Response::download($this->invoices->pdfPath((int) $id), $bundle['invoice']['number'] . '.pdf', 'application/pdf');
    }

    public function send(Request $request, string $id): Response
    {
        $result = $this->invoices->send((int) $id, (int) $this->auth->id());
        flash($result['status'] === 'sent' ? 'success' : 'error', $result['status'] === 'sent' ? __('admin.email_sent') : (string) $result['error']);
        $invoice = $this->db->fetch('SELECT case_id FROM invoices WHERE id = ?', [(int) $id]);
        if (!empty($invoice['case_id'])) {
            return $this->redirect('/admin/cases/' . (int) $invoice['case_id'] . '#finance');
        }

        return $this->redirect('/admin/invoices/' . $id);
    }

    public function cancel(Request $request, string $id): Response
    {
        try {
            $this->invoices->cancel((int) $id, (int) $this->auth->id(), trim((string) $request->input('reason', 'Cancelled')));
            flash('success', __('admin.cancelled'));
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        return $this->redirect('/admin/invoices/' . $id);
    }
}
