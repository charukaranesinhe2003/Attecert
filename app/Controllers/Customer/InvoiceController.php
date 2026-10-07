<?php

declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;
use App\Services\InvoiceService;

final class InvoiceController extends Controller
{
    public function __construct(
        \App\Core\Validator $validator,
        private AuthService $auth,
        private Database $db,
        private InvoiceService $invoices
    ) {
        parent::__construct($validator);
    }

    public function index(): Response
    {
        return $this->view('customer/invoices', [
            'title' => __('portal.invoices'),
            'robots' => 'noindex',
            'active' => 'invoices',
            'invoices' => $this->db->fetchAll(
                'SELECT i.*, c.number AS case_number FROM invoices i INNER JOIN cases c ON c.id = i.case_id WHERE i.customer_id = ? ORDER BY i.created_at DESC',
                [(int) $this->auth->customer()['id']]
            ),
        ], 'layouts/customer');
    }

    public function show(Request $request, string $id): Response
    {
        $bundle = $this->owned((int) $id);

        return $this->view('customer/invoice', [
            'title' => $bundle['invoice']['number'],
            'robots' => 'noindex',
            'active' => 'invoices',
            'bundle' => $bundle,
        ], 'layouts/customer');
    }

    public function pdf(Request $request, string $id): Response
    {
        $bundle = $this->owned((int) $id);
        $path = $this->invoices->pdfPath((int) $id);

        return Response::download($path, $bundle['invoice']['number'] . '.pdf', 'application/pdf');
    }

    public function payments(): Response
    {
        $rows = $this->db->fetchAll(
            'SELECT p.*, i.number AS invoice_number FROM payments p INNER JOIN invoices i ON i.id = p.invoice_id WHERE p.customer_id = ? ORDER BY p.paid_at DESC',
            [(int) $this->auth->customer()['id']]
        );

        return $this->view('customer/payments', [
            'title' => __('portal.payments'),
            'robots' => 'noindex',
            'active' => 'payments',
            'payments' => $rows,
        ], 'layouts/customer');
    }

    private function owned(int $id): array
    {
        $bundle = $this->invoices->bundle($id);
        if ((int) $bundle['customer']['id'] !== (int) $this->auth->customer()['id']) {
            throw new HttpException(404);
        }

        return $bundle;
    }
}
