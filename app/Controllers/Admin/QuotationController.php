<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\QuotationService;

final class QuotationController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private Database $db,
        private QuotationService $quotations,
        private AuthService $auth
    ) {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $sql = 'SELECT q.*, c.number AS case_number, cu.name AS customer_name FROM quotations q INNER JOIN cases c ON c.id = q.case_id INNER JOIN customers cu ON cu.id = q.customer_id WHERE 1 = 1';
        $params = [];
        if ($q !== '') {
            $sql .= ' AND (q.number LIKE ? OR c.number LIKE ? OR cu.name LIKE ?)';
            $like = '%' . $q . '%';
            $params = [$like, $like, $like];
        }
        if ($status !== '') {
            $sql .= ' AND q.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY q.created_at DESC';

        return $this->view('admin/quotations/index', [
            'title' => __('admin.quotations'),
            'robots' => 'noindex',
            'active' => 'quotations',
            'q' => $q,
            'status' => $status,
            'page' => $this->db->paginate($sql, $params, $this->pageQuery($request)),
        ], 'layouts/admin');
    }

    public function show(Request $request, string $id): Response
    {
        return $this->view('admin/quotations/show', [
            'title' => __('admin.quotations'),
            'robots' => 'noindex',
            'active' => 'quotations',
            'bundle' => $this->quotations->bundle((int) $id),
            'versions' => $this->db->fetchAll('SELECT id, version, created_at FROM quotation_versions WHERE quotation_id = ? ORDER BY version', [(int) $id]),
        ], 'layouts/admin');
    }

    public function pdf(Request $request, string $id): Response
    {
        $bundle = $this->quotations->bundle((int) $id);

        return Response::download($this->quotations->pdfPath((int) $id), $bundle['quotation']['number'] . '.pdf', 'application/pdf');
    }

    public function send(Request $request, string $id): Response
    {
        $result = $this->quotations->send((int) $id, (int) $this->auth->id());
        flash($result['status'] === 'sent' ? 'success' : 'error', $result['status'] === 'sent' ? __('admin.email_sent') : (string) $result['error']);

        return $this->redirect('/admin/quotations/' . $id);
    }
}
