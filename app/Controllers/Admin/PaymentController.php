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
        $sql = 'SELECT p.*, i.number AS invoice_number, cu.name AS customer_name FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id INNER JOIN customers cu ON cu.id = p.customer_id ORDER BY p.created_at DESC';

        return $this->view('admin/payments/index', [
            'title' => __('admin.payments'),
            'robots' => 'noindex',
            'active' => 'payments',
            'page' => $this->db->paginate($sql, [], $this->pageQuery($request)),
        ], 'layouts/admin');
    }

    public function store(Request $request, string $id): Response
    {
        $data = $this->validate($request, [
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,bank_transfer,card,online,other,refund',
            'paid_at' => 'required|date',
            'reference' => 'nullable|max:80',
            'receipt_reference' => 'nullable|max:80',
            'notes' => 'nullable|max:1000',
        ]);
        try {
            $paymentId = $this->payments->record((int) $id, $data, (int) $this->auth->id());
            $invoice = $this->db->fetch('SELECT case_id, number FROM invoices WHERE id = ?', [(int) $id]);
            flash('success', __('admin.payment_saved') . ' ' . __('admin.receipt_ready'));

            return $this->redirect('/admin/cases/' . (int) ($invoice['case_id'] ?? 0) . '#finance');
        } catch (\Throwable $exception) {
            flash('error', $exception->getMessage());

            return $this->redirect('/admin/invoices/' . $id);
        }
    }

    public function receipt(Request $request, string $id): Response
    {
        $payment = $this->db->fetch('SELECT * FROM payments WHERE id = ?', [(int) $id]);

        return Response::download($this->payments->receiptPath((int) $id), $payment['number'] . '.pdf', 'application/pdf');
    }
}
