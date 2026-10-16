<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\PaymentService;

final class PaymentController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private Database $db,
        private PaymentService $payments,
        private AuthService $auth
    ) {
        parent::__construct($validator);
    }

    public function index(Request $request): Response
    {
        $q = mb_substr(trim(is_string($request->query('q')) ? $request->query('q') : ''), 0, 190);
        $method = is_string($request->query('method')) ? $request->query('method') : '';
        $method = in_array($method, ['cash', 'bank_transfer', 'card', 'online', 'other', 'refund'], true) ? $method : '';
        $status = is_string($request->query('status')) ? $request->query('status') : '';
        $status = in_array($status, ['completed', 'refunded'], true) ? $status : '';
        $from = is_string($request->query('from')) ? trim($request->query('from')) : '';
        $to = is_string($request->query('to')) ? trim($request->query('to')) : '';
        $rangeErrors = [];
        try {
            $this->validator->validate(['from' => $request->query('from'), 'to' => $request->query('to')], ['from' => 'nullable|date', 'to' => 'nullable|date']);
            if ($from !== '' && $to !== '' && $from > $to) {
                throw new \App\Core\ValidationException(['to' => 'The end date must be on or after the start date.']);
            }
        } catch (\App\Core\ValidationException $exception) {
            $rangeErrors = $exception->errors();
        }
        $sql = 'SELECT p.*, i.number AS invoice_number, cu.name AS customer_name FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id INNER JOIN customers cu ON cu.id = p.customer_id WHERE 1 = 1';
        $params = [];
        foreach (['p.method' => $method, 'p.status' => $status] as $column => $filter) {
            if ($filter !== '') { $sql .= ' AND ' . $column . ' = ?'; $params[] = $filter; }
        }
        if ($q !== '') {
            $sql .= ' AND (p.number LIKE ? OR i.number LIKE ? OR cu.name LIKE ? OR p.reference LIKE ?)';
            array_push($params, ...array_fill(0, 4, '%' . $q . '%'));
        }
        if ($from !== '') { $sql .= ' AND p.paid_at >= ?'; $params[] = $from; }
        if ($to !== '') { $sql .= ' AND p.paid_at <= ?'; $params[] = $to; }
        $sql .= ' ORDER BY p.paid_at DESC, p.id DESC';

        return $this->view('admin/payments/index', [
            'title' => __('admin.payments'),
            'robots' => 'noindex',
            'active' => 'payments',
            'page' => $rangeErrors === [] ? $this->db->paginate($sql, $params, $this->pageQuery($request)) : ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1],
            'filters' => compact('q', 'method', 'status', 'from', 'to'),
            'rangeErrors' => $rangeErrors,
        ], 'layouts/admin', $rangeErrors === [] ? 200 : 422);
    }

    public function store(Request $request, string $id): Response
    {
        try {
        $data = $this->validate($request, [
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,bank_transfer,card,online,other,refund',
            'paid_at' => 'required|date',
            'reference' => 'nullable|max:80',
            'receipt_reference' => 'nullable|max:80',
            'notes' => 'nullable|max:1000',
        ]);
            $paymentId = $this->payments->record((int) $id, $data, (int) $this->auth->id());
            $invoice = $this->db->fetch('SELECT case_id, number FROM invoices WHERE id = ?', [(int) $id]);
            flash('success', __('admin.payment_saved') . ' ' . __('admin.receipt_ready'));

            return $this->redirect('/admin/cases/' . (int) ($invoice['case_id'] ?? 0) . '#finance');
        } catch (\App\Core\ValidationException $exception) {
            $_SESSION['_errors'] = $exception->errors();
            $_SESSION['_old'] = array_diff_key($request->all(), ['_token' => true]);
            $_SESSION['_old']['_formkind'] = $request->input('method') === 'refund' ? 'refund' : 'payment';
            $_SESSION['_old']['invoice_id'] = (string) $id;
            flash('error', implode(' ', $exception->errors()));

            return $this->redirect('/admin/invoices/' . $id);
        } catch (\Throwable $exception) {
            if ($exception instanceof \App\Core\HttpException) {
                throw $exception;
            }
            $_SESSION['_old'] = array_diff_key($request->all(), ['_token' => true]);
            $_SESSION['_old']['_formkind'] = $request->input('method') === 'refund' ? 'refund' : 'payment';
            $_SESSION['_old']['invoice_id'] = (string) $id;
            $_SESSION['_errors'] = ['amount' => $exception->getMessage()];
            flash('error', $exception->getMessage());

            return $this->redirect('/admin/invoices/' . $id);
        }
    }

    public function receipt(Request $request, string $id): Response
    {
        $payment = $this->db->fetch('SELECT * FROM payments WHERE id = ?', [(int) $id]);
        if ($payment === null) {
            throw new \App\Core\HttpException(404, 'Payment not found.');
        }

        return Response::download($this->payments->receiptPath((int) $id), $payment['number'] . '.pdf', 'application/pdf');
    }
}
