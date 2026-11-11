<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\NextStep;
use App\Services\AuthService;
use App\Services\CaseService;
use App\Services\CatalogService;
use App\Services\DocumentService;
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
            'pageHeading' => $this->auth->isRestrictedStaff() ? 'My assigned cases' : __('admin.cases'),
            'pageDescription' => $this->auth->isRestrictedStaff() ? 'You can manage all details of cases assigned to you. Contact an administrator for new assignments.' : __('admin.cases_hint'),
            'pageAction' => $this->auth->can('cases.create') ? '<a class="btn btn-primary" href="' . e(url('/admin/cases/create')) . '">+ ' . e(__('admin.new_case')) . '</a>' : '',
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
            'staff' => app(\App\Services\CaseAccessService::class)->staff(),
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
            'assigned_to' => 'nullable|integer|min:1',
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
        if ($case === null) { throw new HttpException(404); }
        return $this->view('admin/cases/workflow', [
            'title' => $case['number'], 'robots' => 'noindex', 'active' => 'cases', 'hidePageHeader' => true,
            'case' => $case, 'statuses' => $this->workflow->statuses(),
            'steps' => $this->auth->can('cases.edit') ? app(\App\Services\TrackingService::class)->steps((int) $id) : [],
            'payments' => $this->auth->can('payments.view') ? $this->db->fetchAll('SELECT * FROM payments WHERE case_id = ? ORDER BY id DESC', [(int) $id]) : [],
            'invoices' => $this->auth->can('invoices.create') ? $this->db->fetchAll('SELECT * FROM invoices WHERE case_id = ? ORDER BY id DESC', [(int) $id]) : [],
            'quotations' => $this->auth->can('quotations.create') ? $this->db->fetchAll('SELECT id, number, status FROM quotations WHERE case_id = ? ORDER BY id DESC', [(int) $id]) : [],
            'documents' => $this->auth->can('documents.download') ? $this->documents->forCase((int) $id) : [],
        ], 'layouts/admin');
    }

    public function details(Request $request, string $id): Response
    {
        $case = $this->cases->find((int) $id);
        if ($case === null) {
            throw new HttpException(404);
        }

        $canQuote = $this->auth->can('quotations.create');
        $canInvoice = $this->auth->can('invoices.create');
        $canPayment = $this->auth->can('payments.view');
        $quotations = $canQuote ? $this->db->fetchAll('SELECT * FROM quotations WHERE case_id = ? ORDER BY id DESC', [(int) $id]) : [];
        $invoices = $canInvoice ? $this->db->fetchAll('SELECT * FROM invoices WHERE case_id = ? ORDER BY id DESC', [(int) $id]) : [];
        $payments = $canPayment ? $this->db->fetchAll('SELECT * FROM payments WHERE case_id = ? ORDER BY id DESC', [(int) $id]) : [];
        $documents = $this->auth->can('documents.download') ? $this->documents->forCase((int) $case['id']) : [];
        $mofa = $this->db->fetch('SELECT * FROM mofa_processes WHERE case_id = ? ORDER BY id DESC LIMIT 1', [(int) $id]);
        $embassy = $this->db->fetch('SELECT * FROM embassy_processes WHERE case_id = ? ORDER BY id DESC LIMIT 1', [(int) $id]);

        return $this->view('admin/cases/show', [
            'title' => $case['number'],
            'robots' => 'noindex',
            'active' => 'cases',
            'hidePageHeader' => true,
            'case' => $case,
            'statuses' => $this->workflow->statuses(),
            'countries' => $this->catalog->countries(false),
            'services' => $this->catalog->services(false),
            'documentTypes' => $this->catalog->documentTypes(),
            'documents' => $documents,
            'quotations' => $quotations,
            'invoices' => $invoices,
            'payments' => $payments,
            'mofa' => $mofa,
            'embassy' => $embassy,
            'nextStep' => $canQuote && $canInvoice
                ? NextStep::forCase($case, $quotations, $invoices, $mofa, $embassy)
                : ['label' => 'Review the case history and use the actions available to your role.', 'tab' => 'overview'],
            'activity' => $this->activityTimeline((int) $id, $case, $documents, $quotations, $invoices, $payments),
            'staff' => $this->auth->isAdmin() ? app(\App\Services\CaseAccessService::class)->staff() : [],
        ], 'layouts/admin');
    }

    private function activityTimeline(int $caseId, array $case, array $documents, array $quotations, array $invoices, array $payments): array
    {
        $items = [];
        $items[] = [
            'at' => $case['created_at'],
            'title' => 'Case created',
            'meta' => $case['number'] . ' · ' . $case['document_title'],
            'by' => null,
        ];
        foreach ($case['history'] as $item) {
            $items[] = [
                'at' => $item['created_at'],
                'title' => 'Status: ' . ($item['name'] ?: $item['code']),
                'meta' => $item['notes'] ?: null,
                'by' => $item['staff_name'] ?? null,
            ];
        }
        foreach ($documents as $doc) {
            $items[] = [
                'at' => $doc['created_at'],
                'title' => 'Document uploaded',
                'meta' => $doc['original_name'] . ' · ' . $doc['category'],
                'by' => null,
            ];
        }
        foreach ($quotations as $quote) {
            $items[] = [
                'at' => $quote['created_at'],
                'title' => 'Quotation ' . $quote['number'],
                'meta' => $quote['status'] . ' · ' . money($quote['total'], $quote['currency']),
                'by' => null,
            ];
        }
        foreach ($invoices as $invoice) {
            $items[] = [
                'at' => $invoice['created_at'],
                'title' => 'Invoice ' . $invoice['number'],
                'meta' => $invoice['status'] . ' · ' . money($invoice['total'], $invoice['currency']),
                'by' => null,
            ];
        }
        foreach ($payments as $payment) {
            $items[] = [
                'at' => $payment['paid_at'] ?? $payment['created_at'],
                'title' => 'Payment recorded',
                'meta' => money($payment['amount'], $payment['currency'] ?? $case['currency']) . ' · ' . $payment['method'],
                'by' => null,
            ];
        }
        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']));

        return $items;
    }

    public function update(Request $request, string $id): Response
    {
        app(\App\Services\CaseAccessService::class)->requireCase((int) $id);
        $data = $this->validate($request, [
            'document_title' => 'required|min:2|max:180',
            'country_id' => 'nullable|numeric',
            'document_type_id' => 'nullable|numeric',
            'summary' => 'nullable|max:4000',
            'assigned_to' => 'nullable|integer|min:1',
            'estimated_next' => 'nullable|max:180',
            'internal_notes' => 'nullable|max:5000',
            'customer_notes' => 'nullable|max:5000',
        ]);
        if ($this->auth->isRestrictedStaff()) {
            if ($request->input('assigned_to') !== null) {
                $case = app(\App\Services\CaseAccessService::class)->requireCase((int) $id);
                if ((int) $request->input('assigned_to') !== (int) $case['assigned_to']) {
                    throw new HttpException(403, 'Only administrators can reassign cases.');
                }
            }
            unset($data['assigned_to']);
        }
        $this->cases->updateDetails((int) $id, $data, (int) $this->auth->id());
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/cases/' . $id . '#overview');
    }

    public function status(Request $request, string $id): Response
    {
        app(\App\Services\CaseAccessService::class)->requireCase((int) $id);
        $data = $this->validate($request, [
            'status' => 'required|max:40',
            'notes' => 'nullable|max:2000',
        ]);
        $visible = $request->input('customer_visible') ? true : false;
        try {
            $changed = $this->workflow->change((int) $id, $data['status'], (int) $this->auth->id(), $data['notes'] ?? null, $visible, true, (bool) $request->input('confirm_reversal'));
            flash('success', $changed ? __('admin.status_updated') : 'The case already has that status.');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        return $this->redirect('/admin/cases/' . $id . '#overview');
    }

    public function services(Request $request, string $id): Response
    {
        app(\App\Services\CaseAccessService::class)->requireCase((int) $id);
        $quantities = (array) $request->input('quantity', []);
        $prices = (array) $request->input('unit_price', []);
        $lines = [];
        foreach ((array) $request->input('service_id', []) as $serviceId) {
            $lines[] = [
                'service_id' => (int) $serviceId,
                'quantity' => $quantities[$serviceId] ?? 1,
                'unit_price' => $this->auth->can('quotations.create') ? ($prices[$serviceId] ?? '') : '',
            ];
        }
        $this->cases->syncServices((int) $id, $lines, $this->auth->id());
        flash('success', __('admin.saved'));

        return $this->redirect('/admin/cases/' . $id . '#quote');
    }

    public function quotation(Request $request, string $id): Response
    {
        // The former builder endpoint now requires an uploaded quotation PDF.
        return app(QuotationController::class)->uploadForCase($request, (int) $id);
    }

    public function upload(Request $request, string $id): Response
    {
        app(\App\Services\CaseAccessService::class)->requireCase((int) $id);
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
            $delivery = $this->documents->notificationDelivery();
            if ($delivery !== null) {
                flash_mail_result($delivery, __('documents.uploaded'));
            }
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());
        }

        return $this->redirect('/admin/cases/' . $id . '#documents');
    }
}
