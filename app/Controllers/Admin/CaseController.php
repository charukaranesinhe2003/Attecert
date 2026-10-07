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
use App\Services\CatalogService;
use App\Services\DocumentService;
use App\Services\QuotationService;
use App\Services\WorkflowService;

final class CaseController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private Database $db,
        private CaseService $cases,
        private CatalogService $catalog,
        private WorkflowService $workflow,
        private DocumentService $documents,
        private QuotationService $quotations,
        private AuthService $auth
    ) {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        return $this->view('admin/cases/index', [
            'title' => __('admin.cases'),
            'robots' => 'noindex',
            'active' => 'cases',
            'filters' => [
                'q' => trim((string) $request->query('q', '')),
                'status' => (string) $request->query('status', ''),
                'country_id' => (string) $request->query('country_id', ''),
            ],
            'page' => $this->cases->search([
                'q' => trim((string) $request->query('q', '')),
                'status' => (string) $request->query('status', ''),
                'country_id' => (string) $request->query('country_id', ''),
            ], $this->pageQuery($request)),
            'statuses' => $this->workflow->statuses(false),
            'countries' => $this->catalog->countries(false),
        ], 'layouts/admin');
    }

    public function create(Request $request): Response
    {
        return $this->view('admin/cases/create', [
            'title' => __('admin.new_case'),
            'robots' => 'noindex',
            'active' => 'cases',
            'customers' => $this->db->fetchAll('SELECT id, name, email FROM customers WHERE deleted_at IS NULL ORDER BY name LIMIT 200'),
            'countries' => $this->catalog->countries(false),
            'services' => $this->catalog->services(false),
            'documentTypes' => $this->catalog->documentTypes(),
            'staff' => $this->db->fetchAll('SELECT u.id, u.name FROM users u INNER JOIN user_roles ur ON ur.user_id = u.id INNER JOIN roles r ON r.id = ur.role_id WHERE r.code <> \'customer\' AND u.deleted_at IS NULL GROUP BY u.id, u.name ORDER BY u.name'),
        ], 'layouts/admin');
    }

    public function store(Request $request): Response
    {
        $data = $this->validate($request, [
            'customer_id' => 'required|numeric',
            'document_title' => 'required|min:2|max:180',
            'country_id' => 'nullable|numeric',
            'document_type_id' => 'nullable|numeric',
            'summary' => 'nullable|max:4000',
            'assigned_to' => 'nullable|numeric',
        ]);
        $lines = [];
        foreach ((array) $request->input('service_id', []) as $serviceId) {
            $lines[] = ['service_id' => (int) $serviceId, 'quantity' => 1];
        }
        $id = $this->cases->create($data + ['source' => 'admin'], $lines, (int) $this->auth->id());
        flash('success', __('admin.case_created'));

        return $this->redirect('/admin/cases/' . $id);
    }

    public function show(Request $request, string $id): Response
    {
        $case = $this->cases->find((int) $id);
        if ($case === null) {
            throw new HttpException(404);
        }

        return $this->view('admin/cases/show', [
            'title' => $case['number'],
            'robots' => 'noindex',
            'active' => 'cases',
            'case' => $case,
            'statuses' => $this->workflow->statuses(),
            'countries' => $this->catalog->countries(false),
            'services' => $this->catalog->services(false),
            'documentTypes' => $this->catalog->documentTypes(),
            'documents' => $this->documents->forCase((int) $case['id']),
            'quotations' => $this->db->fetchAll('SELECT * FROM quotations WHERE case_id = ? ORDER BY id DESC', [(int) $id]),
            'invoices' => $this->db->fetchAll('SELECT * FROM invoices WHERE case_id = ? ORDER BY id DESC', [(int) $id]),
            'payments' => $this->db->fetchAll('SELECT * FROM payments WHERE case_id = ? ORDER BY id DESC', [(int) $id]),
            'mofa' => $this->db->fetch('SELECT * FROM mofa_processes WHERE case_id = ? ORDER BY id DESC LIMIT 1', [(int) $id]),
            'embassy' => $this->db->fetch('SELECT * FROM embassy_processes WHERE case_id = ? ORDER BY id DESC LIMIT 1', [(int) $id]),
            'staff' => $this->db->fetchAll('SELECT u.id, u.name FROM users u INNER JOIN user_roles ur ON ur.user_id = u.id INNER JOIN roles r ON r.id = ur.role_id WHERE r.code <> \'customer\' AND u.deleted_at IS NULL GROUP BY u.id, u.name'),
        ], 'layouts/admin');
    }

    public function update(Request $request, string $id): Response
    {
        $data = $this->validate($request, [
            'document_title' => 'required|min:2|max:180',
            'country_id' => 'nullable|numeric',
            'document_type_id' => 'nullable|numeric',
            'summary' => 'nullable|max:4000',
            'assigned_to' => 'nullable|numeric',
            'estimated_next' => 'nullable|max:180',
            'internal_notes' => 'nullable|max:5000',
            'customer_notes' => 'nullable|max:5000',
        ]);
        $this->cases->updateDetails((int) $id, $data, (int) $this->auth->id());
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/cases/' . $id);
    }

    public function status(Request $request, string $id): Response
    {
        $data = $this->validate($request, [
            'status' => 'required|max:40',
            'notes' => 'nullable|max:2000',
        ]);
        $visible = $request->input('customer_visible') ? true : false;
        try {
            $this->workflow->change((int) $id, $data['status'], (int) $this->auth->id(), $data['notes'] ?? null, $visible, true);
            flash('success', __('admin.status_updated'));
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        return $this->redirect('/admin/cases/' . $id);
    }

    public function services(Request $request, string $id): Response
    {
        $quantities = (array) $request->input('quantity', []);
        $prices = (array) $request->input('unit_price', []);
        $lines = [];
        foreach ((array) $request->input('service_id', []) as $serviceId) {
            $lines[] = [
                'service_id' => (int) $serviceId,
                'quantity' => $quantities[$serviceId] ?? 1,
                'unit_price' => $prices[$serviceId] ?? '',
            ];
        }
        $this->cases->syncServices((int) $id, $lines);
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/cases/' . $id);
    }

    public function quotation(Request $request, string $id): Response
    {
        $lines = [];
        foreach ((array) $request->input('lines', []) as $serviceId => $line) {
            if (empty($line['selected'])) {
                continue;
            }
            $lines[] = [
                'service_id' => (int) $serviceId,
                'quantity' => $line['qty'] ?? 1,
                'unit_price' => $line['price'] ?? 0,
                'description' => $line['description'] ?? '',
            ];
        }
        if (trim((string) $request->input('extra_description', '')) !== '') {
            $lines[] = [
                'service_id' => null,
                'quantity' => $request->input('extra_qty', 1),
                'unit_price' => $request->input('extra_price', 0),
                'description' => (string) $request->input('extra_description'),
                'pricing_type' => 'custom',
            ];
        }
        try {
            $quotationId = $this->quotations->save((int) $id, [
                'quotation_id' => (int) $request->input('quotation_id', 0),
                'lines' => $lines,
                'discount_amount' => (string) $request->input('discount_amount', '0'),
                'tax_rate' => (string) $request->input('tax_rate', '0'),
                'additional_amount' => (string) $request->input('additional_amount', '0'),
                'notes' => (string) $request->input('notes', ''),
                'terms' => (string) $request->input('terms', ''),
                'validity_days' => (int) $request->input('validity_days', setting('quote_validity_days', 14)),
            ], (int) $this->auth->id(), (bool) $request->input('issue'), $this->auth->can('quotations.approve'));
            if ($request->input('email')) {
                if (!$this->auth->can('quotations.send')) {
                    flash('error', __('admin.email_forbidden'));
                } else {
                    $mail = $this->quotations->send($quotationId, (int) $this->auth->id());
                    flash($mail['status'] === 'sent' ? 'success' : 'error', $mail['status'] === 'sent' ? __('admin.email_sent') : (string) $mail['error']);
                }
            } else {
                flash('success', __('admin.quotation_saved'));
            }

            return $this->redirect('/admin/quotations/' . $quotationId);
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());

            return $this->redirect('/admin/cases/' . $id);
        }
    }

    public function upload(Request $request, string $id): Response
    {
        $file = $request->file('document');
        if ($file === null) {
            flash('error', __('documents.upload_failed'));

            return $this->redirect('/admin/cases/' . $id);
        }
        try {
            $this->documents->store(
                (int) $id,
                null,
                $file,
                (string) $request->input('category', 'customer'),
                (string) $request->input('subcategory', 'other'),
                (string) $request->input('description', ''),
                (bool) $request->input('customer_visible'),
                (int) $this->auth->id()
            );
            flash('success', __('documents.uploaded'));
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        return $this->redirect('/admin/cases/' . $id);
    }
}
