<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\InvoiceService;
use App\Services\QuotationService;

final class QuotationController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private Database $db,
        private QuotationService $quotations,
        private InvoiceService $invoices,
        private AuthService $auth
    ) {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $sql = 'SELECT q.*, c.number AS case_number, cu.name AS customer_name FROM quotations q INNER JOIN cases c ON c.id = q.case_id INNER JOIN customers cu ON cu.id = q.customer_id WHERE c.deleted_at IS NULL AND cu.deleted_at IS NULL';
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
        $returnCase = (int) $request->input('return_case', 0);
        if ($returnCase > 0) {
            return $this->redirect('/admin/cases/' . $returnCase . '#quote');
        }
        $quote = $this->db->fetch('SELECT case_id FROM quotations WHERE id = ?', [(int) $id]);

        return $this->redirect('/admin/cases/' . (int) ($quote['case_id'] ?? 0) . '#quote');
    }

    public function createInvoice(Request $request, string $id): Response
    {
        try {
            $invoiceId = $this->invoices->createFromQuotation((int) $id, (int) $this->auth->id());
            $invoice = $this->db->fetch('SELECT case_id, number FROM invoices WHERE id = ?', [$invoiceId]);
            flash('success', __('admin.invoice_created', ['number' => (string) ($invoice['number'] ?? '')]));

            return $this->redirect('/admin/cases/' . (int) ($invoice['case_id'] ?? 0) . '#finance');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
            $quote = $this->db->fetch('SELECT case_id FROM quotations WHERE id = ?', [(int) $id]);

            return $this->redirect('/admin/cases/' . (int) ($quote['case_id'] ?? 0) . '#finance');
        }
    }
}
