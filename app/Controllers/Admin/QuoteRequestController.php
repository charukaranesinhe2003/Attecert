<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\CaseService;
use App\Services\AuditService;

final class QuoteRequestController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private Database $db,
        private CaseService $cases,
        private AuthService $auth,
        private AuditService $audit
    ) {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $status = is_string($request->query('status')) ? $request->query('status') : '';
        $status = in_array($status, ['new', 'contacted', 'converted', 'closed', 'spam'], true) ? $status : '';
        $q = mb_substr(trim(is_string($request->query('q')) ? $request->query('q') : ''), 0, 190);
        $sql = 'SELECT q.*, c.name AS country_name FROM quote_requests q LEFT JOIN country_translations c ON c.country_id = q.country_id AND c.locale = \'en\' WHERE 1 = 1';
        $sql .= ' AND ' . app(\App\Services\CaseAccessService::class)->quoteScope('q.id');
        $params = [];
        if ($status !== '') {
            $sql .= ' AND q.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $sql .= ' AND (q.name LIKE ? OR q.email LIKE ? OR q.phone LIKE ? OR EXISTS (SELECT 1 FROM cases linked WHERE linked.id = q.converted_case_id AND ' . app(\App\Services\CaseAccessService::class)->scope('linked.id') . ' AND linked.number LIKE ?))';
            array_push($params, ...array_fill(0, 4, '%' . $q . '%'));
        }
        $sql .= ' ORDER BY q.created_at DESC, q.id DESC';

        return $this->view('admin/quotes/index', [
            'title' => __('admin.quote_requests'),
            'robots' => 'noindex',
            'active' => 'quotes',
            'status' => $status,
            'q' => $q,
            'page' => $this->db->paginate($sql, $params, $this->pageQuery($request)),
            'canUploadQuotation' => $this->canUploadQuotation(),
        ], 'layouts/admin');
    }

    public function show(Request $request, string $id): Response
    {
        $quote = app(\App\Services\CaseAccessService::class)->requireQuote((int) $id);

        return $this->view('admin/quotes/show', [
            'title' => __('admin.quote_requests'),
            'robots' => 'noindex',
            'active' => 'quotes',
            'quote' => $quote,
            'linkedCase' => $quote['converted_case_id'] === null ? null : $this->cases->find((int) $quote['converted_case_id']),
            'services' => $this->db->fetchAll('SELECT s.id, t.name FROM quote_request_services qs INNER JOIN services s ON s.id = qs.service_id LEFT JOIN service_translations t ON t.service_id = s.id AND t.locale = \'en\' WHERE qs.quote_request_id = ?', [(int) $id]),
            'files' => $this->db->fetchAll('SELECT * FROM documents WHERE quote_request_id = ? AND deleted_at IS NULL', [(int) $id]),
            'canUploadQuotation' => $this->canUploadQuotation(),
            'quotationCurrency' => $quote['converted_case_id'] === null ? setting('currency', 'LKR') : $this->db->fetchColumn('SELECT currency FROM cases WHERE id = ?', [$quote['converted_case_id']]),
            'quotations' => $this->db->fetchAll('SELECT q.*, d.original_name FROM quotations q LEFT JOIN documents d ON d.id = q.uploaded_document_id WHERE q.quote_request_id = ? ORDER BY q.id DESC', [(int) $id]),
        ], 'layouts/admin');
    }

    public function status(Request $request, string $id): Response
    {
        app(\App\Services\CaseAccessService::class)->requireQuote((int) $id);
        $data = $this->validate($request, ['status' => 'required|in:new,contacted,converted,closed,spam']);
        $this->db->transaction(function () use ($data, $id): void {
            $quote = app(\App\Services\CaseAccessService::class)->requireQuote((int) $id, true);
            if ($data['status'] === 'converted' && $quote['converted_case_id'] === null) {
                throw new \App\Core\ValidationException(['status' => __('admin_lists.convert_required')]);
            }
            if ($quote['status'] !== $data['status']) {
                $this->db->run('UPDATE quote_requests SET status = ? WHERE id = ?', [$data['status'], (int) $id]);
                $this->audit->log($this->auth->id(), 'quote_request_status_updated', 'quote_request', (int) $id, ['previous_status' => $quote['status'], 'status' => $data['status']]);
            }
        });
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/quote-requests/' . $id);
    }

    public function convert(Request $request, string $id): Response
    {
        app(\App\Services\CaseAccessService::class)->requireQuote((int) $id);
        try {
            $caseId = $this->cases->createFromQuote((int) $id, (int) $this->auth->id());
            flash('success', __('admin.converted'));

            if ($this->cases->find($caseId) === null) {
                flash('success', 'This request already has a case. Ask an administrator to assign that case to you in Operations.');
                return $this->redirect('/admin/quote-requests/' . $id);
            }

            return $this->redirect('/admin/cases/' . $caseId);
        } catch (\Throwable $exception) {
            if ($exception instanceof HttpException || $exception instanceof \App\Core\ValidationException) {
                throw $exception;
            }
            error_log('Quote request conversion failed for request ' . $id . ': ' . $exception->getMessage());
            flash('error', 'This request could not be converted. Please try again.');

            return $this->redirect('/admin/quote-requests/' . $id);
        }
    }

    public function download(Request $request, string $id, string $documentId): Response
    {
        app(\App\Services\CaseAccessService::class)->requireQuote((int) $id);
        if ($this->db->fetch('SELECT id FROM documents WHERE id = ? AND quote_request_id = ? AND deleted_at IS NULL', [(int) $documentId, (int) $id]) === null) {
            throw new HttpException(404, 'Document not found.');
        }
        $file = app(\App\Services\DocumentService::class)->download((int) $documentId, null, (int) $this->auth->id(), true);
        return Response::download($file['path'], $file['name'], $file['mime']);
    }

    public function uploadQuotation(Request $request, string $id): Response
    {
        app(\App\Services\CaseAccessService::class)->requireQuote((int) $id);
        if (!$this->canUploadQuotation()) { throw new HttpException(403, 'You cannot upload or email quotations.'); }
        $data = $this->validate($request, ['total' => 'required|numeric|min:0.01', 'validity_days' => 'required|integer|min:1|max:3650', 'notes' => 'nullable|max:4000']);
        $quotationId = $this->db->transaction(function () use ($id, $request, $data): int {
            $quote = app(\App\Services\CaseAccessService::class)->requireQuote((int) $id, true);
            $caseId = $quote['converted_case_id'] === null
                ? $this->cases->createFromQuote((int) $id, (int) $this->auth->id()) : (int) $quote['converted_case_id'];
            return app(\App\Services\QuotationService::class)->upload($caseId, $request->file('quotation_document') ?? [], $data, (int) $this->auth->id(), (int) $id);
        });
        try {
            $delivery = app(\App\Services\QuotationService::class)->send($quotationId, (int) $this->auth->id());
            flash_mail_result($delivery, 'Quotation document uploaded.');
        } catch (\Throwable $exception) {
            flash('error', 'The quotation was saved, but its email could not be queued. Use Email quotation to retry.');
        }
        return $this->redirect('/admin/quote-requests/' . $id . '#uploaded-quotations');
    }

    public function quotationPdf(Request $request, string $id, string $quotationId): Response
    {
        $this->requestQuotation((int) $id, (int) $quotationId);
        $service = app(\App\Services\QuotationService::class);
        return Response::download($service->pdfPath((int) $quotationId), $service->downloadName((int) $quotationId), 'application/pdf');
    }

    public function sendQuotation(Request $request, string $id, string $quotationId): Response
    {
        $quotation = $this->requestQuotation((int) $id, (int) $quotationId);
        if (!$this->canUploadQuotation()) { throw new HttpException(403, 'You cannot email quotations.'); }
        if (!in_array($quotation['status'], ['draft', 'sent', 'viewed'], true)) {
            throw new \App\Core\ValidationException(['quotation' => 'Only current pending quotations can be emailed.']);
        }
        flash_mail_result(app(\App\Services\QuotationService::class)->send((int) $quotationId, (int) $this->auth->id()));
        return $this->redirect('/admin/quote-requests/' . $id . '#uploaded-quotations');
    }

    private function requestQuotation(int $id, int $quotationId): array
    {
        $request = app(\App\Services\CaseAccessService::class)->requireQuote($id);
        $quotation = $this->db->fetch('SELECT q.* FROM quotations q INNER JOIN cases c ON c.id = q.case_id AND c.deleted_at IS NULL
            WHERE q.id = ? AND q.quote_request_id = ? AND q.case_id = ?', [$quotationId, $id, $request['converted_case_id']]);
        if ($quotation === null) { throw new HttpException(404, 'Quotation not found.'); }
        return $quotation;
    }

    private function canUploadQuotation(): bool
    {
        return $this->auth->can('quotations.create') && $this->auth->can('quotations.send');
    }
}
