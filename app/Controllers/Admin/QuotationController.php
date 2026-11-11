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
        $sql .= ' AND ' . app(\App\Services\CaseAccessService::class)->scope('q.case_id');
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
        app(\App\Services\CaseAccessService::class)->requireRecord('quotations', (int) $id);
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
        app(\App\Services\CaseAccessService::class)->requireRecord('quotations', (int) $id);
        $bundle = $this->quotations->bundle((int) $id);

        return Response::download($this->quotations->pdfPath((int) $id), $this->quotations->downloadName((int) $id), 'application/pdf');
    }

    public function upload(Request $request): Response
    {
        $data = $this->validate($request, ['case_id' => 'required|integer|min:1', 'total' => 'required|numeric|min:0.01',
            'validity_days' => 'required|integer|min:1|max:3650', 'notes' => 'nullable|max:4000']);
        return $this->uploadForCase($request, (int) $data['case_id']);
    }

    public function uploadForCase(Request $request, int $caseId): Response
    {
        app(\App\Services\CaseAccessService::class)->requireCase($caseId);
        if (!$this->auth->can('quotations.create') || !$this->auth->can('quotations.send')) {
            throw new \App\Core\HttpException(403, 'You do not have permission to upload and email quotations.');
        }
        $data = $this->validate($request, ['total' => 'required|numeric|min:0.01', 'validity_days' => 'required|integer|min:1|max:3650', 'notes' => 'nullable|max:4000']);
        $id = $this->quotations->upload($caseId, $request->file('quotation_document') ?? [], $data, (int) $this->auth->id());
        try {
            $result = $this->quotations->send($id, (int) $this->auth->id());
            flash_mail_result($result, 'Quotation document uploaded.');
        } catch (\Throwable $exception) {
            flash('error', 'The quotation was saved, but its email could not be queued. Open the quotation to retry sending.');
        }
        return $this->redirect('/admin/quotations/' . $id);
    }

    public function send(Request $request, string $id): Response
    {
        app(\App\Services\CaseAccessService::class)->requireRecord('quotations', (int) $id);
        $bundle = $this->quotations->bundle((int) $id);
        if ($bundle['quotation']['status'] === 'draft' && !$this->auth->can('quotations.approve')) {
            throw new \App\Core\HttpException(403, 'Issuing a draft quotation requires approval permission.');
        }
        $result = $this->quotations->send((int) $id, (int) $this->auth->id());
        flash_mail_result($result);
        $returnCase = (int) $request->input('return_case', 0);
        if ($returnCase > 0) {
            return $this->redirect('/admin/cases/' . $returnCase . '#quote');
        }
        $quote = $this->db->fetch('SELECT case_id FROM quotations WHERE id = ?', [(int) $id]);

        return $this->redirect('/admin/cases/' . (int) ($quote['case_id'] ?? 0) . '#quote');
    }

    public function createInvoice(Request $request, string $id): Response
    {
        app(\App\Services\CaseAccessService::class)->requireRecord('quotations', (int) $id);
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
